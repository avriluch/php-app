<?php

namespace App\Http\Controllers\Api;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Support\ProfilePhotoUrl;
use App\Models\Booking;
use App\Models\PackagePurchase;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class ProfessionalClientController extends Controller
{
    /**
     * Clientes únicos que reservaron o compraron paquetes con el profesional autenticado.
     */
    public function index(Request $request): JsonResponse
    {
        $usuario = $request->user();
        abort_unless($usuario->role === UserRole::Professional, 403);

        $perfil = $usuario->professionalProfile;
        abort_unless($perfil, 404, 'Perfil profesional no encontrado.');

        $perfilId = $perfil->id;
        $ahora = Carbon::now();

        $statsReservas = Booking::query()
            ->where('professional_profile_id', $perfilId)
            ->select('client_user_id')
            ->selectRaw('COUNT(*) as total_reservas')
            ->selectRaw('MAX(fecha_hora) as ultima_reserva')
            ->selectRaw(
                'MIN(CASE WHEN fecha_hora > ? AND estado NOT IN (?, ?) THEN fecha_hora END) as proxima_reserva',
                [
                    $ahora->toDateTimeString(),
                    BookingStatus::Cancelada->value,
                    BookingStatus::NoAsistida->value,
                ]
            )
            ->groupBy('client_user_id')
            ->get()
            ->keyBy('client_user_id');

        $statsPaquetes = PackagePurchase::query()
            ->join('services', 'services.id', '=', 'package_purchases.service_id')
            ->where('services.professional_profile_id', $perfilId)
            ->select('package_purchases.client_user_id')
            ->selectRaw('COUNT(*) as total_paquetes')
            ->selectRaw('MAX(package_purchases.purchased_at) as ultima_compra_paquete')
            ->selectRaw('SUM(package_purchases.sesiones_restantes) as sesiones_paquete_restantes')
            ->groupBy('package_purchases.client_user_id')
            ->get()
            ->keyBy('client_user_id');

        $clientIds = $statsReservas->keys()
            ->merge($statsPaquetes->keys())
            ->unique()
            ->values();

        if ($clientIds->isEmpty()) {
            return response()->json([
                'data' => [],
                'meta' => ['total' => 0],
            ]);
        }

        $clientes = User::query()
            ->whereIn('id', $clientIds)
            ->orderBy('nombre')
            ->get()
            ->map(function (User $cliente) use ($statsReservas, $statsPaquetes) {
                $reserva = $statsReservas->get($cliente->id);
                $paquete = $statsPaquetes->get($cliente->id);

                $ultimaReserva = $reserva?->ultima_reserva
                    ? Carbon::parse($reserva->ultima_reserva)->toIso8601String()
                    : null;
                $ultimaCompra = $paquete?->ultima_compra_paquete
                    ? Carbon::parse($paquete->ultima_compra_paquete)->toIso8601String()
                    : null;

                return [
                    'id' => $cliente->id,
                    'nombre' => $cliente->nombre,
                    'apellido' => $cliente->apellido,
                    'email' => $cliente->email,
                    'telefono' => $cliente->telefono,
                    'foto_perfil' => ProfilePhotoUrl::resolve($cliente->foto_perfil),
                    'total_reservas' => (int) ($reserva?->total_reservas ?? 0),
                    'total_paquetes' => (int) ($paquete?->total_paquetes ?? 0),
                    'sesiones_paquete_restantes' => (int) ($paquete?->sesiones_paquete_restantes ?? 0),
                    'ultima_reserva' => $ultimaReserva,
                    'ultima_compra_paquete' => $ultimaCompra,
                    'ultimo_contacto' => $this->ultimoContacto($ultimaReserva, $ultimaCompra),
                    'proxima_reserva' => $reserva?->proxima_reserva
                        ? Carbon::parse($reserva->proxima_reserva)->toIso8601String()
                        : null,
                ];
            })
            ->sortByDesc('ultimo_contacto')
            ->values()
            ->all();

        $search = trim($request->string('search')->toString());
        if ($search !== '') {
            $needle = mb_strtolower($search);
            $clientes = array_values(array_filter($clientes, function (array $c) use ($needle) {
                $haystack = mb_strtolower(implode(' ', array_filter([
                    $c['nombre'],
                    $c['apellido'],
                    $c['email'],
                    $c['telefono'],
                ])));

                return str_contains($haystack, $needle);
            }));
        }

        return response()->json([
            'data' => $clientes,
            'meta' => ['total' => count($clientes)],
        ]);
    }

    private function ultimoContacto(?string $ultimaReserva, ?string $ultimaCompra): ?string
    {
        $fechas = array_filter([$ultimaReserva, $ultimaCompra]);
        if ($fechas === []) {
            return null;
        }

        return collect($fechas)
            ->map(fn (string $iso) => Carbon::parse($iso))
            ->max()
            ?->toIso8601String();
    }
}
