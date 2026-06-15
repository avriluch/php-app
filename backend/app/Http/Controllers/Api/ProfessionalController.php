<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProfessionalDetailResource;
use App\Http\Resources\ProfessionalListResource;
use App\Http\Resources\ServiceResource;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfessionalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = $this->baseQuery();

        if ($search = $request->string('search')->trim()->toString()) {
            $term = '%' . $search . '%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('titulo', 'like', $term)
                    ->orWhereHas('user', function (Builder $uq) use ($term) {
                        $uq->where('nombre', 'like', $term)
                            ->orWhere('apellido', 'like', $term);
                    });
            });
        }

        // Filtros sobre servicios del profesional: modalidad, tipo, precio.
        $modalidad = $request->string('modalidad')->toString();
        $type = $request->string('type')->toString();
        $precioMin = $request->input('precio_min');
        $precioMax = $request->input('precio_max');

        if ($modalidad || $type || $precioMin !== null || $precioMax !== null) {
            $query->whereHas('services', function (Builder $q) use ($modalidad, $type, $precioMin, $precioMax) {
                $q->where('activo', true);
                if ($modalidad) {
                    $q->where('modalidad', $modalidad);
                }
                if ($type) {
                    $q->where('type', $type);
                }
                if ($precioMin !== null) {
                    $q->where('precio', '>=', (float) $precioMin);
                }
                if ($precioMax !== null) {
                    $q->where('precio', '<=', (float) $precioMax);
                }
            });
        }

        // Filtros sobre ubicación del profesional.
        if ($ciudad = $request->string('ciudad')->toString()) {
            $query->whereHas('location', fn (Builder $q) => $q->where('ciudad', 'like', '%' . $ciudad . '%'));
        }
        if ($pais = $request->string('pais')->toString()) {
            $query->whereHas('location', fn (Builder $q) => $q->where('pais', $pais));
        }

        $lat = $request->input('lat');
        $lng = $request->input('lng');
        $radiusKm = $request->input('radius_km');
        $proximityActive = $lat !== null && $lng !== null;

        if ($proximityActive) {
            $request->validate([
                'lat' => ['required', 'numeric', 'between:-90,90'],
                'lng' => ['required', 'numeric', 'between:-180,180'],
                'radius_km' => ['sometimes', 'numeric', 'min:1', 'max:500'],
            ]);

            $this->applyProximityFilter(
                $query,
                (float) $lat,
                (float) $lng,
                (float) ($radiusKm ?? 25),
            );
        }

        // Filtro por calificación mínima (promedio).
        if ($ratingMin = $request->input('rating_min')) {
            $query->having('reviews_avg_puntaje', '>=', (float) $ratingMin);
        }

        // Orden: rating (default) o price (precio del servicio más barato).
        $sort = $request->string('sort', 'rating')->toString();
        if ($sort === 'distance') {
            abort_unless($proximityActive, 422, 'El orden por cercanía requiere lat y lng.');
            $query->orderBy('distance_km');
        } elseif ($sort === 'price') {
            $query->orderBy('precio_desde');
        } else {
            $query->orderByDesc('reviews_avg_puntaje');
        }

        $profiles = $query->paginate(
            perPage: min((int) $request->input('per_page', 15), 50)
        );

        $profiles->getCollection()->transform(function (ProfessionalProfile $profile) {
            $profile->modalidades = $this->modalidadesPara($profile);

            return $profile;
        });

        return response()->json([
            'data' => ProfessionalListResource::collection($profiles)->resolve(),
            'meta' => [
                'current_page' => $profiles->currentPage(),
                'last_page' => $profiles->lastPage(),
                'per_page' => $profiles->perPage(),
                'total' => $profiles->total(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $profile = $this->baseQuery()
            ->with([
                'services' => fn ($q) => $q->where('activo', true)->with('location')->orderBy('precio'),
                'agenda',
            ])
            ->findOrFail($id);

        $profile->modalidades = $this->modalidadesPara($profile);

        return response()->json(new ProfessionalDetailResource($profile));
    }

    public function services(int $id): JsonResponse
    {
        $perfil = ProfessionalProfile::findOrFail($id);

        $servicios = $perfil->services()
            ->where('activo', true)
            ->with('location')
            ->orderBy('precio')
            ->get();

        return response()->json([
            'data' => ServiceResource::collection($servicios)->resolve(),
        ]);
    }

    public function availability(Request $request, int $id, AvailabilityService $disponibilidad): JsonResponse
    {
        $datos = $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $perfil = ProfessionalProfile::with('agenda')->findOrFail($id);

        if (! $perfil->agenda) {
            return response()->json(['slots' => []]);
        }

        $servicio = Service::findOrFail($datos['service_id']);
        abort_unless(
            (int) $servicio->professional_profile_id === (int) $perfil->id,
            422,
            'El servicio no pertenece al profesional.',
        );

        $desde = Carbon::parse($datos['from']);
        $hasta = Carbon::parse($datos['to']);

        $slots = $disponibilidad->slotsFor($perfil->agenda, $servicio, $desde, $hasta);

        return response()->json([
            'slots' => $slots->values()->all(),
        ]);
    }

    /**
     * Perfil profesional del usuario autenticado (para la pantalla de Configuración).
     */
    public function myProfile(Request $request): JsonResponse
    {
        $perfil = $request->user()->professionalProfile;
        abort_unless($perfil, 404, 'Perfil profesional no encontrado.');

        return response()->json($this->formatOwnProfile($perfil));
    }

    /**
     * Actualiza título, descripción y política de cancelación del profesional.
     */
    public function updateMyProfile(Request $request): JsonResponse
    {
        $perfil = $request->user()->professionalProfile;
        abort_unless($perfil, 404, 'Perfil profesional no encontrado.');

        $datos = $request->validate([
            'titulo' => ['sometimes', 'string', 'max:120'],
            'descripcion' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'cancelacion_horas_minimas' => ['sometimes', 'integer', 'between:0,168'],
        ]);

        $perfil->fill($datos)->save();

        return response()->json([
            'message' => 'Perfil actualizado.',
            'profile' => $this->formatOwnProfile($perfil),
        ]);
    }

    /** @return array<string, mixed> */
    private function formatOwnProfile(ProfessionalProfile $perfil): array
    {
        return [
            'id' => $perfil->id,
            'titulo' => $perfil->titulo,
            'descripcion' => $perfil->descripcion,
            'cancelacion_horas_minimas' => (int) $perfil->cancelacion_horas_minimas,
        ];
    }

    /**
     * Filtra profesionales dentro de un radio (km) usando la fórmula de Haversine.
     */
    private function applyProximityFilter(Builder $query, float $lat, float $lng, float $radiusKm): void
    {
        $inner = 'cos(radians(' . $lat . ')) * cos(radians(locations.latitud))'
            . ' * cos(radians(locations.longitud) - radians(' . $lng . '))'
            . ' + sin(radians(' . $lat . ')) * sin(radians(locations.latitud))';

        $clamped = config('database.default') === 'sqlite'
            ? "(CASE WHEN ({$inner}) > 1 THEN 1 WHEN ({$inner}) < -1 THEN -1 ELSE ({$inner}) END)"
            : "LEAST(1.0, GREATEST(-1.0, ({$inner})))";

        $haversine = "(6371 * acos({$clamped}))";

        $query->whereNotNull('professional_profiles.location_id')
            ->join('locations', 'professional_profiles.location_id', '=', 'locations.id')
            ->select('professional_profiles.*')
            ->selectRaw("{$haversine} AS distance_km")
            ->whereRaw("{$haversine} <= ?", [$radiusKm]);
    }

    private function baseQuery(): Builder
    {
        return ProfessionalProfile::query()
            ->with(['user', 'location'])
            ->withAvg('reviews', 'puntaje')
            ->withCount('reviews')
            ->withMin(['services as precio_desde' => fn (Builder $q) => $q->where('activo', true)], 'precio')
            ->whereHas('user')
            ->whereHas('services', fn (Builder $q) => $q->where('activo', true));
    }

    /** @return list<string> */
    private function modalidadesPara(ProfessionalProfile $profile): array
    {
        if ($profile->relationLoaded('services')) {
            return $profile->services
                ->where('activo', true)
                ->pluck('modalidad')
                ->map(fn ($m) => $m->value)
                ->unique()
                ->values()
                ->all();
        }

        return $profile->services()
            ->where('activo', true)
            ->distinct()
            ->pluck('modalidad')
            ->map(fn ($m) => $m->value)
            ->all();
    }
}
