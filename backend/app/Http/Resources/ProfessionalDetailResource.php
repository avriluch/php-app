<?php

namespace App\Http\Resources;

use App\Support\ProfilePhotoUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfessionalDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $this->user;
        $agenda = $this->agenda;

        return [
            'id' => $this->id,
            'nombre' => $user->nombre,
            'apellido' => $user->apellido,
            'titulo' => $this->titulo,
            'descripcion' => $this->descripcion,
            'foto_perfil' => ProfilePhotoUrl::resolve($user->foto_perfil),
            'telefono' => $user->telefono,
            'rating_avg' => round((float) ($this->reviews_avg_puntaje ?? 0), 1),
            'rating_count' => (int) ($this->reviews_count ?? 0),
            'modalidades' => $this->when(
                isset($this->modalidades),
                $this->modalidades ?? []
            ),
            'ubicacion' => $this->when(
                $this->relationLoaded('location') && $this->location,
                fn () => new LocationResource($this->location)
            ),
            'servicios' => ServiceResource::collection($this->whenLoaded('services')),
            'agenda_resumen' => $this->when($agenda, fn () => [
                'horario_inicio' => substr((string) $agenda->horario_inicio, 0, 5),
                'horario_fin' => substr((string) $agenda->horario_fin, 0, 5),
                'dias_disponibles' => $agenda->dias_disponibles,
                'buffer_minutos' => $agenda->buffer_minutos,
                'pausa_entre_sesiones_minutos' => $agenda->pausa_entre_sesiones_minutos,
            ]),
        ];
    }
}
