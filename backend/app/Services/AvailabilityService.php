<?php

namespace App\Services;

use App\Models\Agenda;
use App\Models\Booking;
use App\Models\Service;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * Servicio de dominio para calcular slots disponibles.
 *
 * TODO (equipo backend):
 * - Respetar agenda (horario, días, buffer)
 * - Excluir agenda_exceptions
 * - Excluir bookings no cancelados que solapen duración del servicio
 * - Devolver Collection de ['start' => ISO8601, 'end' => ISO8601, 'available' => bool]
 */
class AvailabilityService
{
    public function slotsFor(
        Agenda $agenda,
        Service $service,
        Carbon $from,
        Carbon $to,
    ): Collection {
        // Placeholder: implementar lógica real
        return collect();
    }

    public function isSlotFree(
        int $professionalProfileId,
        Carbon $start,
        int $durationMinutes,
        ?int $ignoreBookingId = null,
    ): bool {
        $end = $start->copy()->addMinutes($durationMinutes);

        $query = Booking::query()
            ->where('professional_profile_id', $professionalProfileId)
            ->whereNotIn('estado', ['cancelada'])
            ->when($ignoreBookingId, fn ($q) => $q->where('id', '!=', $ignoreBookingId));

        // TODO: comparar solapamiento real (fecha_hora + duración servicio)
        return ! $query->where('fecha_hora', $start)->exists();
    }
}
