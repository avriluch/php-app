<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LocationResource;
use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Catálogo de ubicaciones físicas. Lo consume el profesional al crear/editar
 * servicios presenciales o híbridos. Las locations son compartidas entre
 * profesionales (no hay FK profesional→location).
 */
class LocationController extends Controller
{
    public function index(): JsonResponse
    {
        $ubicaciones = Location::orderBy('ciudad')->get();

        return response()->json([
            'data' => LocationResource::collection($ubicaciones)->resolve(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'ciudad' => ['required', 'string', 'max:120'],
            'pais' => ['required', 'string', 'size:2'],
            'latitud' => ['required', 'numeric', 'between:-90,90'],
            'longitud' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $ubicacion = Location::create($datos);

        return response()->json(new LocationResource($ubicacion), 201);
    }
}
