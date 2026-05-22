<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TODO: solo el profesional autenticado dueño de la agenda
 */
class AgendaController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return $this->notImplemented('GET /professional/agenda');
    }

    public function update(Request $request): JsonResponse
    {
        return $this->notImplemented('PUT /professional/agenda');
    }

    public function storeException(Request $request): JsonResponse
    {
        return $this->notImplemented('POST /professional/agenda/exceptions');
    }

    public function destroyException(int $id): JsonResponse
    {
        return $this->notImplemented("DELETE /professional/agenda/exceptions/{$id}");
    }

    private function notImplemented(string $endpoint): JsonResponse
    {
        return response()->json([
            'message' => 'Endpoint pendiente de implementación.',
            'endpoint' => $endpoint,
        ], 501);
    }
}
