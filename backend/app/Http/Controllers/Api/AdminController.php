<?php

namespace App\Http\Controllers\Api;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\ProfessionalProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function users(Request $request): JsonResponse
    {
        $query = User::query()
            ->with('professionalProfile:id,user_id,titulo')
            ->orderByDesc('created_at');

        if ($busqueda = $request->string('search')->trim()->toString()) {
            $like = '%' . $busqueda . '%';
            $query->where(function (Builder $q) use ($like) {
                $q->where('nombre', 'like', $like)
                    ->orWhere('apellido', 'like', $like)
                    ->orWhere('email', 'like', $like);
            });
        }

        if ($rol = $request->string('role')->toString()) {
            $query->where('role', $rol);
        }

        $usuarios = $query->paginate(
            perPage: min((int) $request->input('per_page', 20), 100)
        );

        return response()->json([
            'data' => $usuarios->getCollection()->map(fn (User $u) => [
                'id' => $u->id,
                'nombre' => $u->nombre,
                'apellido' => $u->apellido,
                'email' => $u->email,
                'telefono' => $u->telefono,
                'role' => $u->role->value,
                'activo' => (bool) $u->activo,
                'foto_perfil' => $u->foto_perfil,
                'created_at' => $u->created_at?->toIso8601String(),
                'profesional' => $u->professionalProfile ? [
                    'id' => $u->professionalProfile->id,
                    'titulo' => $u->professionalProfile->titulo,
                ] : null,
            ])->all(),
            'meta' => [
                'current_page' => $usuarios->currentPage(),
                'last_page' => $usuarios->lastPage(),
                'total' => $usuarios->total(),
            ],
        ]);
    }

    public function metrics(): JsonResponse
    {
        $inicioMes = Carbon::now()->startOfMonth();
        $finMes = Carbon::now()->endOfMonth();

        $usuariosPorRol = User::query()
            ->selectRaw('role, COUNT(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role')
            ->all();

        $reservasPorEstado = Booking::query()
            ->whereBetween('created_at', [$inicioMes, $finMes])
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado')
            ->all();

        return response()->json([
            'usuarios' => [
                'total' => User::count(),
                'por_rol' => [
                    'client' => (int) ($usuariosPorRol[UserRole::Client->value] ?? 0),
                    'professional' => (int) ($usuariosPorRol[UserRole::Professional->value] ?? 0),
                    'admin' => (int) ($usuariosPorRol[UserRole::Admin->value] ?? 0),
                ],
                'nuevos_mes' => User::whereBetween('created_at', [$inicioMes, $finMes])->count(),
            ],
            'profesionales' => [
                'total' => ProfessionalProfile::count(),
                'con_servicios' => ProfessionalProfile::has('services')->count(),
            ],
            'reservas' => [
                'total_mes' => Booking::whereBetween('created_at', [$inicioMes, $finMes])->count(),
                'por_estado_mes' => $reservasPorEstado,
                'canceladas_mes' => (int) ($reservasPorEstado[BookingStatus::Cancelada->value] ?? 0),
            ],
            'ingresos' => [
                'mes' => (float) Payment::where('estado', PaymentStatus::Completado->value)
                    ->whereBetween('fecha_pago', [$inicioMes, $finMes])
                    ->sum('monto'),
                'total' => (float) Payment::where('estado', PaymentStatus::Completado->value)
                    ->sum('monto'),
            ],
        ]);
    }

    /**
     * Feed unificado de actividad reciente: registros, reservas y pagos,
     * ordenados del más nuevo al más viejo.
     */
    public function activity(): JsonResponse
    {
        $usuarios = User::query()
            ->latest()
            ->limit(8)
            ->get(['id', 'nombre', 'apellido', 'role', 'created_at'])
            ->map(fn (User $u) => [
                'tipo' => 'usuario',
                'descripcion' => trim("{$u->nombre} {$u->apellido}") . ' se registró como ' . $u->role->value,
                'fecha' => $u->created_at?->toIso8601String(),
                'timestamp' => $u->created_at?->timestamp ?? 0,
            ]);

        $reservas = Booking::query()
            ->with(['client:id,nombre,apellido', 'service:id,nombre'])
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (Booking $b) => [
                'tipo' => 'reserva',
                'descripcion' => trim(($b->client?->nombre ?? 'Alguien') . ' ' . ($b->client?->apellido ?? ''))
                    . ' reservó ' . ($b->service?->nombre ?? 'un servicio'),
                'estado' => $b->estado?->value,
                'fecha' => $b->created_at?->toIso8601String(),
                'timestamp' => $b->created_at?->timestamp ?? 0,
            ]);

        $pagos = Payment::query()
            ->where('estado', PaymentStatus::Completado->value)
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (Payment $p) => [
                'tipo' => 'pago',
                'descripcion' => 'Pago de $ ' . number_format((float) $p->monto, 0, ',', '.') . ' recibido',
                'fecha' => $p->created_at?->toIso8601String(),
                'timestamp' => $p->created_at?->timestamp ?? 0,
            ]);

        $feed = $usuarios->concat($reservas)->concat($pagos)
            ->sortByDesc('timestamp')
            ->take(12)
            ->map(fn (array $item) => collect($item)->except('timestamp')->all())
            ->values()
            ->all();

        return response()->json(['data' => $feed]);
    }

    /**
     * Cambia el rol de un usuario. Si pasa a profesional y no tiene perfil, se lo crea.
     */
    public function updateUserRole(Request $request, int $id): JsonResponse
    {
        $usuario = User::findOrFail($id);

        if ($usuario->id === $request->user()->id) {
            return response()->json(['message' => 'No podés cambiar tu propio rol.'], 422);
        }

        $datos = $request->validate([
            'role' => ['required', 'string', 'in:client,professional,admin'],
        ]);

        $nuevoRol = UserRole::from($datos['role']);

        if ($nuevoRol === UserRole::Professional && ! $usuario->professionalProfile) {
            ProfessionalProfile::create([
                'user_id' => $usuario->id,
                'titulo' => 'Profesional',
            ]);
        }

        $usuario->role = $nuevoRol;
        $usuario->save();

        return response()->json([
            'message' => 'Rol actualizado.',
            'role' => $usuario->role->value,
        ]);
    }

    /**
     * Activa o suspende un usuario. Al suspender, se cierran sus sesiones activas.
     */
    public function updateUserStatus(Request $request, int $id): JsonResponse
    {
        $usuario = User::findOrFail($id);

        if ($usuario->id === $request->user()->id) {
            return response()->json(['message' => 'No podés suspender tu propia cuenta.'], 422);
        }

        $datos = $request->validate([
            'activo' => ['required', 'boolean'],
        ]);

        $usuario->activo = $datos['activo'];
        $usuario->save();

        if (! $usuario->activo) {
            $usuario->tokens()->delete();
        }

        return response()->json([
            'message' => $usuario->activo ? 'Usuario activado.' : 'Usuario suspendido.',
            'activo' => $usuario->activo,
        ]);
    }
}
