<?php

namespace App\Jobs;

use App\Models\Booking;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use App\Services\BrevoMailService;

class EnviarCancelacionReserva implements ShouldQueue
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

        if (! $reserva) {
            return;
        }

        $cliente = $reserva->client;
        $profUser = $reserva->professionalProfile?->user;

        $brevoMail = app(BrevoMailService::class);

        // La notificación in-app ya se creó de forma síncrona en el controller
        // (NotificacionService). Acá solo enviamos el email a ambos.

        if ($cliente) {
            $brevoMail->send(
                $cliente->email,
                'Reserva cancelada',
                'mail.reserva-cancelada',
                [
                    'reserva' => $reserva,
                    'destinatario' => 'cliente',
                ]
            );
        }

        if ($profUser) {
            $brevoMail->send(
                $profUser->email,
                'Reserva cancelada',
                'mail.reserva-cancelada',
                [
                    'reserva' => $reserva,
                    'destinatario' => 'profesional',
                ]
            );
        }
    }
}