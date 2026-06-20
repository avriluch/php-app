<?php

namespace App\Jobs;

use App\Models\Booking;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use App\Services\BrevoMailService;

/**
 * Envía el email de confirmación al cliente y al profesional.
 * La notificación in-app y el broadcast en vivo se hacen de forma síncrona
 * en el controller (NotificacionService + evento), no acá.
 */
class EnviarConfirmacionReserva implements ShouldQueue
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
            'payment',
        ])->find($this->reservaId);

        if (! $reserva) {
            return;
        }

        $profUser = $reserva->professionalProfile?->user;
        $cliente = $reserva->client;

        $brevoMail = app(BrevoMailService::class);

        // La notificación in-app ya se creó de forma síncrona en el controller
        // (NotificacionService). Acá solo enviamos el email y el broadcast.

        // Email al cliente
        if ($cliente) {
            $brevoMail->send(
                $cliente->email,
                'Reserva confirmada con ' . ($profUser?->nombre ?? 'tu profesional'),
                'mail.reserva-creada',
                ['reserva' => $reserva, 'destinatario' => 'cliente'],
            );
        }

        // Email al profesional
        if ($profUser) {
            $brevoMail->send(
                $profUser->email,
                'Nueva reserva: ' . ($cliente?->nombre ?? 'Cliente') . ' ' . ($cliente?->apellido ?? ''),
                'mail.reserva-creada',
                ['reserva' => $reserva, 'destinatario' => 'profesional'],
            );
        }
    }
}
