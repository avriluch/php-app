<?php

namespace App\Jobs;

use App\Enums\NotificationType;
use App\Events\NuevaReservaProfesional;
use App\Models\Booking;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use App\Services\BrevoService;

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
                'tipo' => NotificationType::Confirmacion,
                'mensaje' => 'Tu reserva con ' . ($profUser?->nombre ?? 'tu profesional')
                    . ' fue creada para el ' . $fechaFormateada . '.',
                'fecha_envio' => Carbon::now(),
            ]);

            $brevo->sendView(
                $cliente->email,
                'Reserva confirmada',
                'emails.reserva_creada',
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
                'tipo' => NotificationType::Confirmacion,
                'mensaje' => 'Nueva reserva de ' . ($cliente?->nombre ?? 'un cliente')
                    . ' para el ' . $fechaFormateada . '.',
                'fecha_envio' => Carbon::now(),
            ]);

            $brevo->sendView(
                $profUser->email,
                'Nueva reserva: ' . ($cliente?->nombre ?? 'Cliente') . ' ' . ($cliente?->apellido ?? ''),
                'emails.reserva_creada',
                [
                    'reserva' => $reserva,
                    'destinatario' => 'profesional',
                ]
            );

            /*
            |-----------------------------------------
            | EVENTO EN TIEMPO REAL (Reverb)
            |-----------------------------------------
            */
            try {
                NuevaReservaProfesional::dispatch($reserva);
            } catch (\Throwable $e) {
                Log::warning(
                    'No se pudo transmitir NuevaReservaProfesional: ' . $e->getMessage()
                );
            }
        }
    }
}