<?php

namespace App\Events;

use App\Models\Booking;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReservaCancelada implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Booking $reserva)
    {
    }

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        $profUserId = $this->reserva->professionalProfile?->user_id;
        $clienteUserId = $this->reserva->client_user_id;

        return [
            new PrivateChannel('App.Models.User.' . $profUserId),
            new PrivateChannel('App.Models.User.' . $clienteUserId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'reserva-cancelada';
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
            ],
        ];
    }
}