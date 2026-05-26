<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'estado' => $this->estado->value,
            'fecha_hora' => optional($this->fecha_hora)?->toIso8601String(),
            'modalidad' => $this->modalidad->value,
            'url_video_llamada' => $this->url_video_llamada,
            'cancelled_at' => optional($this->cancelled_at)?->toIso8601String(),
            'cancel_motivo' => $this->cancel_motivo,
            'package_purchase_id' => $this->package_purchase_id,
            'service' => $this->when(
                $this->relationLoaded('service') && $this->service,
                fn () => [
                    'id' => $this->service->id,
                    'nombre' => $this->service->nombre,
                    'duracion' => $this->service->duracion,
                    'precio' => (float) $this->service->precio,
                    'type' => $this->service->type->value,
                ],
            ),
            'professional' => $this->when(
                $this->relationLoaded('professionalProfile') && $this->professionalProfile,
                fn () => [
                    'id' => $this->professionalProfile->id,
                    'nombre' => $this->professionalProfile->user?->nombre,
                    'apellido' => $this->professionalProfile->user?->apellido,
                    'titulo' => $this->professionalProfile->titulo,
                ],
            ),
            'client' => $this->when(
                $this->relationLoaded('client') && $this->client,
                fn () => [
                    'id' => $this->client->id,
                    'nombre' => $this->client->nombre,
                    'apellido' => $this->client->apellido,
                    'email' => $this->client->email,
                ],
            ),
            'payment' => $this->when(
                $this->relationLoaded('payment') && $this->payment,
                fn () => [
                    'id' => $this->payment->id,
                    'estado' => $this->payment->estado->value,
                    'monto' => (float) $this->payment->monto,
                    'metodo' => $this->payment->metodo,
                ],
            ),
            'created_at' => optional($this->created_at)?->toIso8601String(),
        ];
    }
}
