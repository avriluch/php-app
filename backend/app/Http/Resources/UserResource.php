<?php

namespace App\Http\Resources;

use App\Support\ProfilePhotoUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'apellido' => $this->apellido,
            'email' => $this->email,
            'telefono' => $this->telefono,
            'foto_perfil' => ProfilePhotoUrl::resolve($this->foto_perfil),
            'role' => $this->role->value,
            'activo' => (bool) $this->activo,
            'professional_profile_id' => $this->when(
                $this->relationLoaded('professionalProfile'),
                fn () => $this->professionalProfile?->id,
            ),
        ];
    }
}
