<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Agenda;
use App\Models\Booking;
use App\Models\PlatformSetting;
use App\Models\Service;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class AvailabilityService
{
    /**
     * Genera slots disponibles para un profesional en el rango [desde, hasta],
     * respetando agenda (horario, días, buffer, pausa entre sesiones), excepciones y reservas activas.
     *
     * @return Collection<int, array{start:string, end:string, available:bool}>
     */
    public function slotsFor(
        Agenda $agenda,
        Service $servicio,
        Carbon $desde,
        Carbon $hasta,
    ): Collection {
        $duracionMinutos = (int) $servicio->duracion;
        if ($duracionMinutos <= 0) {
            return collect();
        }

        $bufferMinutos = (int) $agenda->buffer_minutos;
        $pausaMinutos = (int) $agenda->pausa_entre_sesiones_minutos;
        $pasoMinutos = $duracionMinutos + $bufferMinutos;
        $diasDisponibles = array_map('intval', $agenda->dias_disponibles ?? []);

        $fechasExcepcion = $agenda->exceptions()
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->pluck('fecha')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->all();
        $fechasExcepcion = array_flip($fechasExcepcion);

        $reservasActivas = $this->cargarReservasActivas(
            $agenda->professional_profile_id,
            $desde->copy()->startOfDay(),
            $hasta->copy()->endOfDay(),
            pausaMinutos: $pausaMinutos,
        );

        $slots = collect();
        $ahora = Carbon::now();
        $antelacionHoras = (int) PlatformSetting::current()->antelacion_reserva_min_horas;
        $limiteAntelacion = $antelacionHoras > 0 ? $ahora->copy()->addHours($antelacionHoras) : $ahora;
        // El rango [desde, hasta] se interpreta como días completos: desde el inicio
        // del día "desde" hasta el fin del día "hasta", inclusive.
        $diaActual = $desde->copy()->startOfDay();
        $diaFinal = $hasta->copy()->startOfDay();
        $limiteSuperior = $hasta->copy()->endOfDay();

        while ($diaActual->lte($diaFinal)) {
            $diaSemana = $diaActual->dayOfWeek;
            $fechaStr = $diaActual->toDateString();
            $esExcepcion = isset($fechasExcepcion[$fechaStr]);

            if (in_array($diaSemana, $diasDisponibles, true) && ! $esExcepcion) {
                [$inicioJornada, $finJornada] = $this->ventanaDelDia($diaActual, $agenda);
                $inicioSlot = $inicioJornada->copy();

                while ($inicioSlot->copy()->addMinutes($duracionMinutos)->lte($finJornada)) {
                    $finSlot = $inicioSlot->copy()->addMinutes($duracionMinutos);

                    // No ofrecer turnos que ya empezaron ni dentro de la antelación mínima.
                    if ($inicioSlot->lt($limiteAntelacion)) {
                        $inicioSlot->addMinutes($pasoMinutos);
                        continue;
                    }

                    if ($finSlot->gte($desde) && $inicioSlot->lte($limiteSuperior)) {
                        $disponible = ! $this->seSolapa(
                            $inicioSlot,
                            $finSlot,
                            $reservasActivas,
                        );

                        $slots->push([
                            'start' => $inicioSlot->toIso8601String(),
                            'end' => $finSlot->toIso8601String(),
                            'available' => $disponible,
                        ]);
                    }

                    $inicioSlot->addMinutes($pasoMinutos);
                }
            }

            $diaActual->addDay();
        }

        return $slots;
    }

    /**
     * Verifica si un slot específico está libre (sin solapar con reservas activas).
     * Usado por BookingController::store dentro de la transacción.
     */
    public function isSlotFree(
        int $professionalProfileId,
        Carbon $inicio,
        int $duracionMinutos,
        ?int $ignorarReservaId = null,
        ?int $pausaMinutos = null,
    ): bool {
        if ($inicio->lt(Carbon::now())) {
            return false;
        }

        $antelacionHoras = (int) PlatformSetting::current()->antelacion_reserva_min_horas;
        if ($antelacionHoras > 0 && $inicio->lt(Carbon::now()->addHours($antelacionHoras))) {
            return false;
        }

        if ($pausaMinutos === null) {
            $pausaMinutos = (int) Agenda::query()
                ->where('professional_profile_id', $professionalProfileId)
                ->value('pausa_entre_sesiones_minutos');
        }

        $fin = $inicio->copy()->addMinutes($duracionMinutos);

        $reservas = $this->cargarReservasActivas(
            $professionalProfileId,
            $inicio->copy()->subDay(),
            $fin->copy()->addDay(),
            $ignorarReservaId,
            $pausaMinutos,
        );

        return ! $this->seSolapa($inicio, $fin, $reservas);
    }

    /**
     * @return array<int, array{inicio:CarbonImmutable, fin:CarbonImmutable}>
     */
    private function cargarReservasActivas(
        int $professionalProfileId,
        Carbon $desde,
        Carbon $hasta,
        ?int $ignorarReservaId = null,
        int $pausaMinutos = 0,
    ): array {
        $reservas = Booking::query()
            ->with('service:id,duracion')
            ->where('professional_profile_id', $professionalProfileId)
            ->whereNotIn('estado', [
                BookingStatus::Cancelada->value,
                BookingStatus::NoAsistida->value,
            ])
            ->whereBetween('fecha_hora', [$desde, $hasta])
            ->when($ignorarReservaId, fn ($q) => $q->where('id', '!=', $ignorarReservaId))
            ->get();

        return $reservas->map(function (Booking $r) use ($pausaMinutos) {
            $inicio = CarbonImmutable::instance($r->fecha_hora);
            $duracion = (int) ($r->service?->duracion ?? 0);

            return [
                'inicio' => $inicio,
                'fin' => $inicio->addMinutes($duracion + $pausaMinutos),
            ];
        })->all();
    }

    /**
     * @param  array<int, array{inicio:CarbonImmutable, fin:CarbonImmutable}>  $reservas
     */
    private function seSolapa(Carbon $inicioSlot, Carbon $finSlot, array $reservas): bool
    {
        foreach ($reservas as $r) {
            // Solapamiento si: reservaInicio < finSlot AND reservaFin > inicioSlot
            if ($r['inicio']->lt($finSlot) && $r['fin']->gt($inicioSlot)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{0:Carbon, 1:Carbon} [inicioJornada, finJornada] del día
     */
    private function ventanaDelDia(Carbon $dia, Agenda $agenda): array
    {
        [$hInicio, $mInicio] = $this->parsearHora($agenda->horario_inicio);
        [$hFin, $mFin] = $this->parsearHora($agenda->horario_fin);

        return [
            $dia->copy()->setTime($hInicio, $mInicio),
            $dia->copy()->setTime($hFin, $mFin),
        ];
    }

    /** @return array{0:int, 1:int} */
    private function parsearHora(string $hora): array
    {
        $partes = explode(':', $hora);

        return [(int) ($partes[0] ?? 0), (int) ($partes[1] ?? 0)];
    }
}
