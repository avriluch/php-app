<?php

namespace App\Http\Controllers\Api;

use App\Enums\Modalidad;
use App\Enums\ServiceType;
use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * CRUD del profesional sobre sus propios servicios.
 * Todas las rutas viven bajo /professional/services y exigen role:professional.
 */
class ServiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perfil = $request->user()->professionalProfile;
        abort_unless($perfil, 404, 'Perfil profesional no encontrado.');

        $servicios = Service::with('location')
            ->where('professional_profile_id', $perfil->id)
            ->orderByDesc('activo')
            ->orderBy('precio')
            ->get();

        return response()->json([
            'data' => ServiceResource::collection($servicios)->resolve(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $perfil = $request->user()->professionalProfile;
        abort_unless($perfil, 404, 'Perfil profesional no encontrado.');

        if (! $perfil->agenda()->exists()) {
            throw ValidationException::withMessages([
                'agenda' => [
                    'Configurá tu agenda antes de publicar servicios. Sin horarios definidos, los clientes no pueden reservar turnos.',
                ],
            ]);
        }

        $datos = $this->validarPayload($request);

        $servicio = Service::create([
            'professional_profile_id' => $perfil->id,
            'type' => $datos['type'],
            'nombre' => $datos['nombre'],
            'descripcion' => $datos['descripcion'] ?? null,
            'duracion' => $datos['duracion'] ?? null,
            'precio' => $datos['precio'],
            'modalidad' => $datos['modalidad'],
            'location_id' => $datos['location_id'] ?? null,
            'cantidad_sesiones' => $datos['type'] === ServiceType::Package->value
                ? ($datos['cantidad_sesiones'] ?? null)
                : null,
            'activo' => $datos['activo'] ?? true,
        ]);

        $servicio->load('location');

        return response()->json(new ServiceResource($servicio), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $perfil = $request->user()->professionalProfile;
        abort_unless($perfil, 404);

        $servicio = Service::where('professional_profile_id', $perfil->id)
            ->where('id', $id)
            ->firstOrFail();

        $datos = $this->validarPayload($request, true);

        foreach (['type', 'nombre', 'descripcion', 'duracion', 'precio', 'modalidad', 'location_id', 'cantidad_sesiones', 'activo'] as $campo) {
            if (array_key_exists($campo, $datos)) {
                $servicio->{$campo} = $datos[$campo];
            }
        }

        // Si dejó de ser paquete, limpiar cantidad_sesiones
        if (($servicio->type instanceof ServiceType ? $servicio->type->value : $servicio->type) !== ServiceType::Package->value) {
            $servicio->cantidad_sesiones = null;
        }

        $servicio->save();
        $servicio->load('location');

        return response()->json(new ServiceResource($servicio));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $perfil = $request->user()->professionalProfile;
        abort_unless($perfil, 404);

        $servicio = Service::where('professional_profile_id', $perfil->id)
            ->where('id', $id)
            ->firstOrFail();

        // Soft-disable en lugar de borrado físico para preservar reservas históricas.
        $servicio->update(['activo' => false]);

        return response()->json(['disabled' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validarPayload(Request $request, bool $parcial = false): array
    {
        $reglaBase = $parcial ? ['sometimes'] : ['required'];

        $datos = $request->validate([
            'type' => [...$reglaBase, Rule::in(array_column(ServiceType::cases(), 'value'))],
            'nombre' => [...$reglaBase, 'string', 'max:150'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'duracion' => ['nullable', 'integer', 'between:5,600'],
            'precio' => [...$reglaBase, 'numeric', 'min:0'],
            'modalidad' => [...$reglaBase, Rule::in(array_column(Modalidad::cases(), 'value'))],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'cantidad_sesiones' => ['nullable', 'integer', 'between:1,200'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        $this->validarCoherencia($datos, $parcial);

        return $datos;
    }

    /**
     * Reglas cruzadas entre campos (no se pueden expresar con $request->validate solo).
     * @param  array<string, mixed>  $datos
     */
    private function validarCoherencia(array $datos, bool $parcial): bool
    {
        $type = $datos['type'] ?? null;
        $modalidad = $datos['modalidad'] ?? null;

        if (! $parcial || isset($datos['type'])) {
            if ($type === ServiceType::Session->value && empty($datos['duracion'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'duracion' => 'La duración es obligatoria para servicios tipo sesión.',
                ]);
            }
            if ($type === ServiceType::Package->value && empty($datos['cantidad_sesiones'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'cantidad_sesiones' => 'La cantidad de sesiones es obligatoria para servicios tipo paquete.',
                ]);
            }
        }

        if (! $parcial || isset($datos['modalidad'])) {
            $necesitaUbicacion = in_array($modalidad, [
                Modalidad::Presencial->value,
                Modalidad::Hibrida->value,
            ], true);
            if ($necesitaUbicacion && empty($datos['location_id'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'location_id' => 'Se requiere una ubicación para servicios presenciales o híbridos.',
                ]);
            }
        }

        return true;
    }
}
