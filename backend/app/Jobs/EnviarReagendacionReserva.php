<?php

namespace App\Jobs;

use App\Enums\NotificationType;
use App\Mail\ReservaReagendadaMail;
use App\Models\Booking;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

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
        $fechaFormateada = $reserva->fecha_hora?->format('d/m/Y H:i');

        if ($cliente) {
            Notification::create([
                'user_id' => $cliente->id,
                'booking_id' => $reserva->id,
                'tipo' => NotificationType::Reagendacion,
                'mensaje' => 'Tu reserva fue reagendada para el ' . $fechaFormateada . '.',
                'fecha_envio' => Carbon::now(),
            ]);

            Mail::to($cliente->email)->send(new ReservaReagendadaMail($reserva, 'cliente', $this->fechaAnterior));
        }

        if ($profUser) {
            Notification::create([
                'user_id' => $profUser->id,
                'booking_id' => $reserva->id,
                'tipo' => NotificationType::Reagendacion,
                'mensaje' => 'La reserva fue reagendada para el ' . $fechaFormateada . '.',
                'fecha_envio' => Carbon::now(),
            ]);

            Mail::to($profUser->email)->send(new ReservaReagendadaMail($reserva, 'profesional', $this->fechaAnterior));
        }
    }
}
