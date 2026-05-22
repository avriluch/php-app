<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfessionalListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $this->user;

        return [
            'id' => $this->id,
            'nombre' => $user->nombre,
            'apellido' => $user->apellido,
            'titulo' => $this->titulo,
            'foto_perfil' => $user->foto_perfil,
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
        ];
    }
}
