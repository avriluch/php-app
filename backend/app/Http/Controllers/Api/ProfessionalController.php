<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProfessionalDetailResource;
use App\Http\Resources\ProfessionalListResource;
use App\Models\ProfessionalProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfessionalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = $this->baseQuery();

        if ($search = $request->string('search')->trim()->toString()) {
            $term = '%'.$search.'%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('titulo', 'like', $term)
                    ->orWhereHas('user', function (Builder $uq) use ($term) {
                        $uq->where('nombre', 'like', $term)
                            ->orWhere('apellido', 'like', $term);
                    });
            });
        }

        if ($modalidad = $request->string('modalidad')->toString()) {
            $query->whereHas('services', function (Builder $q) use ($modalidad) {
                $q->where('activo', true)->where('modalidad', $modalidad);
            });
        }

        $sort = $request->string('sort', 'rating')->toString();
        if ($sort === 'rating') {
            $query->orderByDesc('reviews_avg_puntaje');
        } else {
            $query->orderBy('titulo');
        }

        $profiles = $query->paginate(
            perPage: min((int) $request->input('per_page', 15), 50)
        );

        $profiles->getCollection()->transform(function (ProfessionalProfile $profile) {
            $profile->modalidades = $this->modalidadesFor($profile);

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

        $profile->modalidades = $this->modalidadesFor($profile);

        return response()->json(new ProfessionalDetailResource($profile));
    }

    public function services(int $id): JsonResponse
    {
        return $this->notImplemented("GET /professionals/{$id}/services");
    }

    public function availability(Request $request, int $id): JsonResponse
    {
        return $this->notImplemented("GET /professionals/{$id}/availability");
    }

    private function baseQuery(): Builder
    {
        return ProfessionalProfile::query()
            ->with(['user', 'location'])
            ->withAvg('reviews', 'puntaje')
            ->withCount('reviews')
            ->whereHas('user')
            ->whereHas('services', fn (Builder $q) => $q->where('activo', true));
    }

    /** @return list<string> */
    private function modalidadesFor(ProfessionalProfile $profile): array
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

    private function notImplemented(string $endpoint): JsonResponse
    {
        return response()->json([
            'message' => 'Endpoint pendiente de implementación.',
            'endpoint' => $endpoint,
        ], 501);
    }
}
