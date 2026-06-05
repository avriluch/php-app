<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentStatus;
use App\Enums\ServiceType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\PackagePurchase;
use App\Models\Payment;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PackagePurchaseController extends Controller
{
    /**
     * Paquetes comprados por el cliente autenticado.
     */
    public function index(Request $request): JsonResponse
    {
        $usuario = $request->user();
        abort_unless($usuario->role === UserRole::Client, 403);

        $compras = PackagePurchase::with(['service.professionalProfile.user', 'payment'])
            ->where('client_user_id', $usuario->id)
            ->orderByDesc('purchased_at')
            ->paginate(
                perPage: min((int) $request->input('per_page', 20), 100)
            );

        return response()->json([
            'data' => $compras->getCollection()->map(fn (PackagePurchase $c) => $this->formatPurchase($c))->all(),
            'meta' => [
                'current_page' => $compras->currentPage(),
                'last_page' => $compras->lastPage(),
                'total' => $compras->total(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $usuario = $request->user();
        abort_unless($usuario->role === UserRole::Client, 403);

        $compra = PackagePurchase::with(['service.professionalProfile.user', 'payment'])
            ->where('client_user_id', $usuario->id)
            ->findOrFail($id);

        return response()->json($this->formatPurchase($compra));
    }

    private function formatPurchase(PackagePurchase $c): array
    {
        return [
            'id' => $c->id,
            'sesiones_restantes' => (int) $c->sesiones_restantes,
            'purchased_at' => $c->purchased_at?->toIso8601String(),
            'service' => $c->service ? [
                'id' => $c->service->id,
                'nombre' => $c->service->nombre,
                'precio' => (float) $c->service->precio,
                'cantidad_sesiones' => $c->service->cantidad_sesiones,
                'duracion' => $c->service->duracion,
                'modalidad' => $c->service->modalidad?->value,
            ] : null,
            'profesional' => $c->service?->professionalProfile ? [
                'id' => $c->service->professionalProfile->id,
                'nombre' => $c->service->professionalProfile->user?->nombre,
                'apellido' => $c->service->professionalProfile->user?->apellido,
            ] : null,
            'payment' => $c->payment ? [
                'id' => $c->payment->id,
                'estado' => $c->payment->estado->value,
                'monto' => (float) $c->payment->monto,
            ] : null,
        ];
    }

    public function store(Request $request): JsonResponse
    {
        $usuario = $request->user();
        abort_unless($usuario->role === UserRole::Client, 403, 'Solo clientes pueden comprar paquetes.');

        $datos = $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,id'],
        ]);

        return DB::transaction(function () use ($datos, $usuario) {
            $servicio = Service::lockForUpdate()->findOrFail($datos['service_id']);

            abort_unless(
                $servicio->type === ServiceType::Package,
                422,
                'El servicio indicado no es un paquete.',
            );
            abort_unless($servicio->activo, 422, 'El paquete no está activo.');
            abort_unless(
                $servicio->cantidad_sesiones && $servicio->cantidad_sesiones > 0,
                422,
                'El paquete no tiene una cantidad de sesiones definida.',
            );

            $compra = PackagePurchase::create([
                'client_user_id' => $usuario->id,
                'service_id' => $servicio->id,
                'sesiones_restantes' => (int) $servicio->cantidad_sesiones,
                'purchased_at' => Carbon::now(),
            ]);

            Payment::create([
                'package_purchase_id' => $compra->id,
                'monto' => (float) $servicio->precio,
                'estado' => PaymentStatus::Pendiente->value,
            ]);

            $compra->load(['service.professionalProfile.user', 'payment']);

            return response()->json([
                'id' => $compra->id,
                'sesiones_restantes' => (int) $compra->sesiones_restantes,
                'purchased_at' => $compra->purchased_at?->toIso8601String(),
                'service' => [
                    'id' => $compra->service->id,
                    'nombre' => $compra->service->nombre,
                    'precio' => (float) $compra->service->precio,
                    'cantidad_sesiones' => $compra->service->cantidad_sesiones,
                ],
                'payment' => [
                    'id' => $compra->payment->id,
                    'estado' => $compra->payment->estado->value,
                    'monto' => (float) $compra->payment->monto,
                ],
            ], 201);
        });
    }

    /**
     * Compras de paquetes de los servicios del profesional autenticado.
     */
    public function indexProfessional(Request $request): JsonResponse
    {
        $usuario = $request->user();
        abort_unless($usuario->role === UserRole::Professional, 403);

        $perfil = $usuario->professionalProfile;
        abort_unless($perfil, 404, 'Perfil profesional no encontrado.');

        $compras = PackagePurchase::with(['service', 'client', 'payment'])
            ->whereHas('service', fn ($q) => $q->where('professional_profile_id', $perfil->id))
            ->orderByDesc('purchased_at')
            ->paginate(
                perPage: min((int) $request->input('per_page', 20), 100)
            );

        return response()->json([
            'data' => $compras->getCollection()->map(fn (PackagePurchase $c) => [
                'id' => $c->id,
                'sesiones_restantes' => (int) $c->sesiones_restantes,
                'purchased_at' => $c->purchased_at?->toIso8601String(),
                'service' => $c->service ? [
                    'id' => $c->service->id,
                    'nombre' => $c->service->nombre,
                    'cantidad_sesiones' => $c->service->cantidad_sesiones,
                ] : null,
                'cliente' => $c->client ? [
                    'id' => $c->client->id,
                    'nombre' => $c->client->nombre,
                    'apellido' => $c->client->apellido,
                    'email' => $c->client->email,
                ] : null,
                'payment' => $c->payment ? [
                    'id' => $c->payment->id,
                    'estado' => $c->payment->estado->value,
                    'monto' => (float) $c->payment->monto,
                ] : null,
            ])->all(),
            'meta' => [
                'current_page' => $compras->currentPage(),
                'last_page' => $compras->lastPage(),
                'total' => $compras->total(),
            ],
        ]);
    }
}
