<?php

namespace App\Http\Resources;

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
            'foto_perfil' => $this->foto_perfil,
            'role' => $this->role->value,
            'professional_profile_id' => $this->when(
                $this->relationLoaded('professionalProfile'),
                fn () => $this->professionalProfile?->id,
            ),
        ];
    }
}
