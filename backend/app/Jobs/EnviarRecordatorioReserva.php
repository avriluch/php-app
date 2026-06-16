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

        if (! $reserva) {
            return;
        }

        $cliente = $reserva->client;
        $profUser = $reserva->professionalProfile?->user;

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
                'tipo' => NotificationType::Recordatorio,
                'mensaje' => 'Tenés una sesión próxima el ' 
                    . $reserva->fecha_hora?->format('d/m/Y H:i') . '.',
                'fecha_envio' => Carbon::now(),
            ]);

            $brevo->sendView(
                $cliente->email,
                'Recordatorio de tu reserva',
                'emails.recordatorio_reserva',
                [
                    'reserva' => $reserva,
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
                'tipo' => NotificationType::Recordatorio,
                'mensaje' => 'Tenés una sesión programada el ' 
                    . $reserva->fecha_hora?->format('d/m/Y H:i') . '.',
                'fecha_envio' => Carbon::now(),
            ]);

            $brevo->sendView(
                $profUser->email,
                'Recordatorio de sesión',
                'emails.recordatorio_reserva',
                [
                    'reserva' => $reserva,
                ]
            );
        }
    }
}