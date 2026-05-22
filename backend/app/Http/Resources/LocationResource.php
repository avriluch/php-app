<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ciudad' => $this->ciudad,
            'pais' => $this->pais,
            'latitud' => (float) $this->latitud,
            'longitud' => (float) $this->longitud,
        ];
    }
}
