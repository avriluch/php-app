<?php

namespace App\Events;

use App\Models\Booking;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReservaReagendada implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Booking $reserva,
        public ?string $fechaAnterior = null
    ) {
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
        return 'reserva-reagendada';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'reserva_id' => $this->reserva->id,
            'fecha_hora_nueva' => $this->reserva->fecha_hora?->toIso8601String(),
            'fecha_hora_anterior' => $this->fechaAnterior,

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