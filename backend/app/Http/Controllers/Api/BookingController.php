<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TODO: implementar con DB::transaction y unique(professional_profile_id, fecha_hora)
 *
 * - index, show, store
 * - cancel, reschedule, updateStatus
 */
class BookingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return $this->notImplemented('GET /bookings');
    }

    public function show(int $id): JsonResponse
    {
        return $this->notImplemented("GET /bookings/{$id}");
    }

    public function store(Request $request): JsonResponse
    {
        return $this->notImplemented('POST /bookings');
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        return $this->notImplemented("PATCH /bookings/{$id}/cancel");
    }

    public function reschedule(Request $request, int $id): JsonResponse
    {
        return $this->notImplemented("PATCH /bookings/{$id}/reschedule");
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        return $this->notImplemented("PATCH /bookings/{$id}/status");
    }

    private function notImplemented(string $endpoint): JsonResponse
    {
        return response()->json([
            'message' => 'Endpoint pendiente de implementación.',
            'endpoint' => $endpoint,
            'hint' => 'Usar BookingStatus::canTransitionTo() y bloqueo en transacción.',
        ], 501);
    }
}
