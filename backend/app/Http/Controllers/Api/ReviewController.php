<?php

namespace App\Http\Controllers\Api;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Review;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReviewController extends Controller
{
    /**
     * Listado público de reseñas para un profesional (página detalle).
     */
    public function porProfesional(Request $request, int $professionalId): JsonResponse
    {
        $reviews = Review::with('client:id,nombre,apellido,foto_perfil')
            ->where('professional_profile_id', $professionalId)
            ->orderByDesc('fecha')
            ->paginate(
                perPage: min((int) $request->input('per_page', 10), 50)
            );

        return response()->json([
            'data' => $reviews->getCollection()->map(fn (Review $r) => [
                'id' => $r->id,
                'puntaje' => (float) $r->puntaje,
                'comentario' => $r->comentario,
                'fecha' => $r->fecha->toIso8601String(),
                'cliente' => $r->client ? [
                    'nombre' => $r->client->nombre,
                    'apellido' => $r->client->apellido,
                    'foto_perfil' => $r->client->foto_perfil,
                ] : null,
            ])->all(),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'total' => $reviews->total(),
            ],
        ]);
    }

    /**
     * Reseñas que el cliente autenticado escribió.
     */
    public function mias(Request $request): JsonResponse
    {
        $usuario = $request->user();
        abort_unless($usuario->role === UserRole::Client, 403);

        $reviews = Review::with(['professionalProfile.user:id,nombre,apellido'])
            ->where('client_user_id', $usuario->id)
            ->orderByDesc('fecha')
            ->paginate(
                perPage: min((int) $request->input('per_page', 15), 50)
            );

        return response()->json([
            'data' => $reviews->getCollection()->map(fn (Review $r) => [
                'id' => $r->id,
                'booking_id' => $r->booking_id,
                'puntaje' => (float) $r->puntaje,
                'comentario' => $r->comentario,
                'fecha' => $r->fecha->toIso8601String(),
                'profesional' => $r->professionalProfile ? [
                    'id' => $r->professionalProfile->id,
                    'nombre' => $r->professionalProfile->user?->nombre,
                    'apellido' => $r->professionalProfile->user?->apellido,
                ] : null,
            ])->all(),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'total' => $reviews->total(),
            ],
        ]);
    }

    public function store(Request $request, int $bookingId): JsonResponse
    {
        $usuario = $request->user();
        abort_unless($usuario->role === UserRole::Client, 403, 'Solo clientes pueden calificar.');

        $reserva = Booking::findOrFail($bookingId);

        abort_unless(
            (int) $reserva->client_user_id === (int) $usuario->id,
            403,
            'No es tu reserva.',
        );

        if ($reserva->estado !== BookingStatus::Finalizada) {
            throw ValidationException::withMessages([
                'estado' => 'Solo se califican reservas finalizadas.',
            ]);
        }

        if (Review::where('booking_id', $reserva->id)->exists()) {
            throw ValidationException::withMessages([
                'booking_id' => 'Esta reserva ya fue calificada.',
            ]);
        }

        $datos = $request->validate([
            'puntaje' => ['required', 'numeric', 'between:1,5'],
            'comentario' => ['nullable', 'string', 'max:1000'],
        ]);

        $review = Review::create([
            'booking_id' => $reserva->id,
            'professional_profile_id' => $reserva->professional_profile_id,
            'client_user_id' => $usuario->id,
            'puntaje' => $datos['puntaje'],
            'comentario' => $datos['comentario'] ?? null,
            'fecha' => Carbon::now(),
        ]);

        return response()->json([
            'id' => $review->id,
            'puntaje' => (float) $review->puntaje,
            'comentario' => $review->comentario,
            'fecha' => $review->fecha->toIso8601String(),
        ], 201);
    }
}
