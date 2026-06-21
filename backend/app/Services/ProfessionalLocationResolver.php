<?php

namespace App\Services;

use App\Enums\Modalidad;
use App\Models\Location;
use App\Models\ProfessionalProfile;
use Illuminate\Database\Eloquent\Builder;

/**
 * Resuelve la ubicación pública de un profesional para mapa, filtros y cercanía.
 * Las ubicaciones de servicios presenciales/híbridos tienen prioridad sobre la del perfil.
 */
class ProfessionalLocationResolver
{
    /** @return list<string> */
    private function modalidadesConUbicacion(): array
    {
        return [Modalidad::Presencial->value, Modalidad::Hibrida->value];
    }

    public function displayLocation(ProfessionalProfile $profile): ?Location
    {
        if ($profile->relationLoaded('services')) {
            foreach ($profile->services as $service) {
                if ($service->relationLoaded('location') && $service->location) {
                    return $service->location;
                }
            }
        }

        return $profile->location;
    }

    public function applyCiudadFilter(Builder $query, string $ciudad): void
    {
        $term = '%' . $ciudad . '%';

        $query->where(function (Builder $q) use ($term) {
            $q->whereHas('location', fn (Builder $lq) => $lq->where('ciudad', 'like', $term))
                ->orWhereHas('services', function (Builder $sq) use ($term) {
                    $sq->where('activo', true)
                        ->whereNotNull('location_id')
                        ->whereIn('modalidad', $this->modalidadesConUbicacion())
                        ->whereHas('location', fn (Builder $lq) => $lq->where('ciudad', 'like', $term));
                });
        });
    }

    public function applyProximityFilter(Builder $query, float $lat, float $lng, float $radiusKm): void
    {
        $profileDistance = $this->haversineExpression('pl.latitud', 'pl.longitud', $lat, $lng);
        $serviceDistance = $this->haversineExpression('sl.latitud', 'sl.longitud', $lat, $lng);

        $query->where(function (Builder $outer) use ($profileDistance, $serviceDistance, $radiusKm) {
            $outer->whereExists(function ($sub) use ($profileDistance, $radiusKm) {
                $sub->selectRaw('1')
                    ->from('locations as pl')
                    ->whereColumn('pl.id', 'professional_profiles.location_id')
                    ->whereRaw("{$profileDistance} <= ?", [$radiusKm]);
            })->orWhereExists(function ($sub) use ($serviceDistance, $radiusKm) {
                $sub->selectRaw('1')
                    ->from('services as s')
                    ->join('locations as sl', 'sl.id', '=', 's.location_id')
                    ->whereColumn('s.professional_profile_id', 'professional_profiles.id')
                    ->where('s.activo', true)
                    ->whereNotNull('s.location_id')
                    ->whereIn('s.modalidad', $this->modalidadesConUbicacion())
                    ->whereRaw("{$serviceDistance} <= ?", [$radiusKm]);
            });
        });

        $minDistance = $this->minDistanceExpression($lat, $lng);

        $query->select('professional_profiles.*');
        $query->selectRaw("({$minDistance}) AS distance_km");
    }

    private function haversineExpression(string $latCol, string $lngCol, float $lat, float $lng): string
    {
        $inner = 'cos(radians(' . $lat . ')) * cos(radians(' . $latCol . '))'
            . ' * cos(radians(' . $lngCol . ') - radians(' . $lng . '))'
            . ' + sin(radians(' . $lat . ')) * sin(radians(' . $latCol . '))';

        $clamped = config('database.default') === 'sqlite'
            ? "(CASE WHEN ({$inner}) > 1 THEN 1 WHEN ({$inner}) < -1 THEN -1 ELSE ({$inner}) END)"
            : "LEAST(1.0, GREATEST(-1.0, ({$inner})))";

        return "(6371 * acos({$clamped}))";
    }

    private function minDistanceExpression(float $lat, float $lng): string
    {
        $profileDistance = $this->haversineExpression('pl.latitud', 'pl.longitud', $lat, $lng);
        $serviceDistance = $this->haversineExpression('sl.latitud', 'sl.longitud', $lat, $lng);

        return "(
            SELECT MIN(dist_km) FROM (
                SELECT {$profileDistance} AS dist_km
                FROM locations pl
                WHERE pl.id = professional_profiles.location_id
                UNION ALL
                SELECT {$serviceDistance} AS dist_km
                FROM services s
                INNER JOIN locations sl ON sl.id = s.location_id
                WHERE s.professional_profile_id = professional_profiles.id
                  AND s.activo = 1
                  AND s.location_id IS NOT NULL
                  AND s.modalidad IN ('presencial', 'hibrida')
            ) AS distances
        )";
    }
}
