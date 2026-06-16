<?php

namespace App\Jobs;

use App\Enums\NotificationType;
use App\Models\Booking;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use App\Services\BrevoService;

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

        $cliente = $reserva->client;
        $profUser = $reserva->professionalProfile?->user;

        $fechaFormateada = $reserva->fecha_hora?->format('d/m/Y H:i');

        $brevo = app(BrevoService::class);

        /*
        |-----------------------------------------
        | CLIENTE
        |-----------------------------------------
        */
        if ($cliente) {

            Notification::create([
                'user_id' => $cliente->id,
                'booking_id' => $reserva->id,
                'tipo' => NotificationType::Reagendacion,
                'mensaje' => 'Tu reserva fue reagendada para el ' . $fechaFormateada . '.',
                'fecha_envio' => Carbon::now(),
            ]);

            $brevo->sendView(
                $cliente->email,
                'Reserva reagendada',
                'emails.reserva_reagendada',
                [
                    'reserva' => $reserva,
                    'destinatario' => 'cliente',
                    'fechaAnterior' => $this->fechaAnterior,
                ]
            );
        }

        /*
        |-----------------------------------------
        | PROFESIONAL
        |-----------------------------------------
        */
        if ($profUser) {

            Notification::create([
                'user_id' => $profUser->id,
                'booking_id' => $reserva->id,
                'tipo' => NotificationType::Reagendacion,
                'mensaje' => 'La reserva fue reagendada para el ' . $fechaFormateada . '.',
                'fecha_envio' => Carbon::now(),
            ]);

            $brevo->sendView(
                $profUser->email,
                'Reserva reagendada',
                'emails.reserva_reagendada',
                [
                    'reserva' => $reserva,
                    'destinatario' => 'profesional',
                    'fechaAnterior' => $this->fechaAnterior,
                ]
            );
        }
    }
}