<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'duracion' => $this->duracion,
            'precio' => (float) $this->precio,
            'modalidad' => $this->modalidad->value,
            'cantidad_sesiones' => $this->cantidad_sesiones,
            'activo' => (bool) $this->activo,
            'location_id' => $this->location_id,
            'ubicacion' => $this->when(
                $this->relationLoaded('location') && $this->location,
                fn () => new LocationResource($this->location)
            ),
        ];
    }
}
