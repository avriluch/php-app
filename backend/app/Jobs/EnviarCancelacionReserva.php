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

        $profUser = $reserva->professionalProfile?->user;
        $cliente = $reserva->client;

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
                'tipo' => NotificationType::Cancelacion,
                'mensaje' => 'Tu reserva del ' . $fechaFormateada . ' fue cancelada.',
                'fecha_envio' => Carbon::now(),
            ]);

            $brevo->sendView(
                $cliente->email,
                'Reserva cancelada',
                'emails.reserva_cancelada',
                [
                    'reserva' => $reserva,
                    'destinatario' => 'cliente',
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
                'tipo' => NotificationType::Cancelacion,
                'mensaje' => 'La reserva de ' . ($cliente?->nombre ?? 'un cliente')
                    . ' del ' . $fechaFormateada . ' fue cancelada.',
                'fecha_envio' => Carbon::now(),
            ]);

            $brevo->sendView(
                $profUser->email,
                'Reserva cancelada',
                'emails.reserva_cancelada',
                [
                    'reserva' => $reserva,
                    'destinatario' => 'profesional',
                ]
            );
        }
    }
}