<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Enums\NotificationType;
use App\Jobs\EnviarRecordatorioReserva;
use App\Models\Booking;
use App\Models\Notification;
use App\Models\PlatformSetting;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Despacha jobs de recordatorio para las reservas confirmadas/pagadas
 * que ocurren en las próximas ~24h y que aún no fueron recordadas.
 *
 * Se programa cada hora desde routes/console.php.
 */
class EnviarRecordatoriosReservas extends Command
{
    protected $signature = 'bookings:enviar-recordatorios
        {--horas= : Ventana en horas para considerar "próxima" la reserva}
        {--tolerancia=1 : Tolerancia adicional en horas (para no perder turnos por desfasaje del scheduler)}';

    protected $description = 'Despacha recordatorios para reservas próximas (T+~24h por defecto).';

    public function handle(): int
    {
        $horas = $this->option('horas') !== null
            ? (int) $this->option('horas')
            : (int) PlatformSetting::current()->recordatorio_horas_antes;
        $tolerancia = (int) $this->option('tolerancia');

        $desde = Carbon::now()->addHours($horas - $tolerancia);
        $hasta = Carbon::now()->addHours($horas + $tolerancia);

        $reservas = Booking::query()
            ->whereIn('estado', [
                BookingStatus::Confirmada->value,
                BookingStatus::Pagada->value,
            ])
            ->whereBetween('fecha_hora', [$desde, $hasta])
            ->whereDoesntHave('client.notifications', function ($q) {
                $q->where('tipo', NotificationType::Recordatorio->value)
                    ->whereColumn('notifications.booking_id', 'bookings.id');
            })
            ->get();

        $despachados = 0;
        foreach ($reservas as $reserva) {
            // Doble verificación a nivel SQL por si User.notifications relación da false positives
            $yaNotificado = Notification::where('booking_id', $reserva->id)
                ->where('tipo', NotificationType::Recordatorio->value)
                ->exists();

            if ($yaNotificado) {
                continue;
            }

            EnviarRecordatorioReserva::dispatch($reserva->id);
            $despachados++;
        }

        $this->info("Recordatorios despachados: {$despachados} (rango {$desde} a {$hasta})");

        return self::SUCCESS;
    }
}
