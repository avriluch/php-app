<?php

namespace App\Jobs;

use App\Enums\NotificationType;
use App\Mail\ReservaCanceladaMail;
use App\Models\Booking;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

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

        if ($cliente) {
            Notification::create([
                'user_id' => $cliente->id,
                'booking_id' => $reserva->id,
                'tipo' => NotificationType::Cancelacion,
                'mensaje' => 'Tu reserva del ' . $fechaFormateada . ' fue cancelada.',
                'fecha_envio' => Carbon::now(),
            ]);

            Mail::to($cliente->email)->send(new ReservaCanceladaMail($reserva, 'cliente'));
        }

        if ($profUser) {
            Notification::create([
                'user_id' => $profUser->id,
                'booking_id' => $reserva->id,
                'tipo' => NotificationType::Cancelacion,
                'mensaje' => 'La reserva de ' . ($cliente?->nombre ?? 'un cliente')
                    . ' del ' . $fechaFormateada . ' fue cancelada.',
                'fecha_envio' => Carbon::now(),
            ]);

            Mail::to($profUser->email)->send(new ReservaCanceladaMail($reserva, 'profesional'));
        }
    }
}
