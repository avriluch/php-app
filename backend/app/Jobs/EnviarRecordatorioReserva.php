<?php

namespace App\Jobs;

use App\Enums\NotificationType;
use App\Models\Booking;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use App\Services\BrevoMailService;

/**
 * Recordatorio único al cliente para una reserva próxima.
 * Lo despacha el command `bookings:enviar-recordatorios`.
 */
class EnviarRecordatorioReserva implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public int $reservaId)
    {
    }

    public function handle(): void
    {
        $reserva = Booking::with([
            'client',
            'professionalProfile.user',
            'service',
        ])->find($this->reservaId);

        if (! $reserva || ! $reserva->client) {
            return;
        }

        $fechaFormateada = $reserva->fecha_hora?->format('d/m/Y H:i');

        Notification::create([
            'user_id' => $reserva->client->id,
            'booking_id' => $reserva->id,
            'tipo' => NotificationType::Recordatorio,
            'mensaje' => 'Recordatorio: tu sesión del ' . $fechaFormateada . ' es mañana.',
            'fecha_envio' => Carbon::now(),
        ]);

        app(BrevoMailService::class)->send(
            $reserva->client->email,
            'Recordatorio: tu reserva es mañana',
            'mail.reserva-recordatorio',
            ['reserva' => $reserva],
        );
    }
}
