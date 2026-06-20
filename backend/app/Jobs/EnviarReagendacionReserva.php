<?php

namespace App\Jobs;

use App\Models\Booking;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use App\Services\BrevoMailService;

class EnviarReagendacionReserva implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(
        public int $reservaId,
        public ?string $fechaAnterior = null,
    ) {
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

        $profUser = $reserva->professionalProfile?->user;
        $cliente = $reserva->client;

        $brevoMail = app(BrevoMailService::class);

        // La notificación in-app ya se creó de forma síncrona en el controller
        // (NotificacionService). Acá solo enviamos el email a ambos.

        if ($cliente) {
            $brevoMail->send(
                $cliente->email,
                'Reserva reagendada',
                'mail.reserva-reagendada',
                ['reserva' => $reserva, 'destinatario' => 'cliente', 'fechaAnterior' => $this->fechaAnterior],
            );
        }

        if ($profUser) {
            $brevoMail->send(
                $profUser->email,
                'Reserva reagendada',
                'mail.reserva-reagendada',
                ['reserva' => $reserva, 'destinatario' => 'profesional', 'fechaAnterior' => $this->fechaAnterior],
            );
        }
    }
}
