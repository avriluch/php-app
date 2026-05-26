<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $usuario = $request->user();
        $soloSinLeer = $request->boolean('unread');

        $query = Notification::where('user_id', $usuario->id)
            ->orderByDesc('fecha_envio')
            ->when($soloSinLeer, fn ($q) => $q->whereNull('read_at'));

        $notificaciones = $query->paginate(
            perPage: min((int) $request->input('per_page', 20), 100)
        );

        $sinLeer = Notification::where('user_id', $usuario->id)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'data' => $notificaciones->getCollection()->map(fn (Notification $n) => [
                'id' => $n->id,
                'tipo' => $n->tipo->value,
                'mensaje' => $n->mensaje,
                'booking_id' => $n->booking_id,
                'fecha_envio' => $n->fecha_envio?->toIso8601String(),
                'read_at' => $n->read_at?->toIso8601String(),
            ])->all(),
            'meta' => [
                'current_page' => $notificaciones->currentPage(),
                'last_page' => $notificaciones->lastPage(),
                'total' => $notificaciones->total(),
                'unread_count' => $sinLeer,
            ],
        ]);
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        $usuario = $request->user();

        $notificacion = Notification::where('user_id', $usuario->id)
            ->where('id', $id)
            ->firstOrFail();

        if ($notificacion->read_at === null) {
            $notificacion->update(['read_at' => Carbon::now()]);
        }

        return response()->json(['read_at' => $notificacion->read_at?->toIso8601String()]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $usuario = $request->user();

        $afectadas = Notification::where('user_id', $usuario->id)
            ->whereNull('read_at')
            ->update(['read_at' => Carbon::now()]);

        return response()->json(['marked' => $afectadas]);
    }
}
