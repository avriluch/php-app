<?php

namespace App\Events;

use App\Models\Booking;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Se transmite al canal privado del profesional cuando entra una reserva nueva.
 * El frontend (Laravel Echo) escucha y muestra una notificación en vivo.
 */
class NuevaReservaProfesional implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Booking $reserva)
    {
    }

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        $profUserId = $this->reserva->professionalProfile?->user_id;

        return [
            new PrivateChannel('App.Models.User.' . $profUserId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'nueva-reserva';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'reserva_id' => $this->reserva->id,
            'fecha_hora' => $this->reserva->fecha_hora?->toIso8601String(),
            'cliente' => [
                'nombre' => $this->reserva->client?->nombre,
                'apellido' => $this->reserva->client?->apellido,
            ],
            'servicio' => [
                'nombre' => $this->reserva->service?->nombre,
                'duracion' => $this->reserva->service?->duracion,
            ],
            'modalidad' => $this->reserva->modalidad?->value,
        ];
    }
}
