<?php

namespace App\Http\Resources;

use App\Support\ProfilePhotoUrl;
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
            'categoria' => $this->categoria,
            'categoria_label' => $this->categoria
                ? (config('professional_categories')[$this->categoria] ?? $this->categoria)
                : null,
            'foto_perfil' => ProfilePhotoUrl::resolve($user->foto_perfil),
            'rating_avg' => round((float) ($this->reviews_avg_puntaje ?? 0), 1),
            'rating_count' => (int) ($this->reviews_count ?? 0),
            'precio_desde' => $this->precio_desde !== null ? (float) $this->precio_desde : null,
            'modalidades' => $this->when(
                isset($this->modalidades),
                $this->modalidades ?? []
            ),
            'ubicacion' => $this->when(
                $this->relationLoaded('location') && $this->location,
                fn () => new LocationResource($this->location)
            ),
            'distance_km' => $this->when(
                isset($this->distance_km),
                fn () => round((float) $this->distance_km, 1)
            ),
        ];
    }
}
