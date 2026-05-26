<?php

namespace App\Jobs;

use App\Enums\NotificationType;
use App\Mail\ReservaRecordatorioMail;
use App\Models\Booking;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

/**
 * Recordatorio único al cliente para una reserva próxima.
 * Lo despacha el command `bookings:enviar-recordatorios`.
 */
class EnviarRecordatorioReserva implements ShouldQueue
{
    use Queueable;

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

        Mail::to($reserva->client->email)->send(new ReservaRecordatorioMail($reserva));
    }
}
