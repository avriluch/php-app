<?php

namespace App\Http\Controllers\Api;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\Booking;
use App\Models\PlatformSetting;
use App\Models\ProfessionalProfile;
use App\Models\User;
use App\Services\NotificacionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly NotificacionService $notificaciones,
    ) {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $settings = PlatformSetting::current();

        if (! $settings->registro_abierto) {
            throw ValidationException::withMessages([
                'email' => ['El registro de nuevas cuentas está temporalmente cerrado.'],
            ]);
        }

        $data = $request->validated();

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'nombre' => $data['nombre'],
                'apellido' => $data['apellido'],
                'email' => $data['email'],
                'password' => $data['password'],
                'telefono' => $data['telefono'] ?? null,
                'role' => UserRole::from($data['role']),
            ]);

            if ($user->role === UserRole::Professional) {
                ProfessionalProfile::create([
                    'user_id' => $user->id,
                    'titulo' => $data['titulo'] ?? 'Profesional',
                    'categoria' => $data['categoria'] ?? null,
                    'descripcion' => $data['descripcion'] ?? null,
                ]);
            }

            return $user;
        });

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Credenciales incorrectas.'],
            ]);
        }

        if (! $user->activo) {
            throw ValidationException::withMessages([
                'email' => ['Tu cuenta está suspendida. Contactá al administrador.'],
            ]);
        }

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(new UserResource(
            $request->user()->load('professionalProfile')
        ));
    }

    public function updateMe(Request $request): JsonResponse
    {
        $usuario = $request->user();

        $datos = $request->validate([
            'nombre' => ['sometimes', 'string', 'max:100'],
            'apellido' => ['sometimes', 'required', 'string', 'max:100'],
            'telefono' => ['sometimes', 'nullable', 'string', 'max:30'],
            'foto_perfil' => ['sometimes', 'nullable', 'string', 'max:500'],
            'email' => ['sometimes', 'email', 'max:150', 'unique:users,email,' . $usuario->id],
            'password' => ['sometimes', 'string', 'min:6', 'confirmed'],
            'password_actual' => ['required_with:password', 'string'],
        ]);

        if (isset($datos['password'])) {
            if (! Hash::check($datos['password_actual'], $usuario->password)) {
                throw ValidationException::withMessages([
                    'password_actual' => ['Contraseña actual incorrecta.'],
                ]);
            }
            $usuario->password = $datos['password'];
        }

        foreach (['nombre', 'apellido', 'telefono', 'foto_perfil', 'email'] as $campo) {
            if (array_key_exists($campo, $datos)) {
                $usuario->{$campo} = $datos[$campo];
            }
        }

        $usuario->save();

        return response()->json(new UserResource($usuario->load('professionalProfile')));
    }

    public function uploadAvatar(Request $request): JsonResponse
    {
        $usuario = $request->user();

        $datos = $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ]);

        $this->eliminarAvatarLocal($usuario->foto_perfil);

        $ruta = $datos['avatar']->store('avatars', 'public');
        $usuario->foto_perfil = '/storage/'.$ruta;
        $usuario->save();

        $usuario->load('professionalProfile');

        return response()->json([
            'message' => 'Foto de perfil actualizada.',
            'user' => (new UserResource($usuario))->resolve(),
        ]);
    }

    public function deleteAvatar(Request $request): JsonResponse
    {
        $usuario = $request->user();

        $this->eliminarAvatarLocal($usuario->foto_perfil);
        $usuario->foto_perfil = null;
        $usuario->save();

        $usuario->load('professionalProfile');

        return response()->json([
            'message' => 'Foto de perfil eliminada.',
            'user' => (new UserResource($usuario))->resolve(),
        ]);
    }

    private function eliminarAvatarLocal(?string $url): void
    {
        if (! $url) {
            return;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (! $path || ! str_contains($path, '/storage/avatars/')) {
            return;
        }

        $relativo = ltrim(str_replace('/storage/', '', $path), '/');
        if ($relativo !== '') {
            Storage::disk('public')->delete($relativo);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Sesión cerrada.']);
    }

    /**
     * Desactiva la cuenta del usuario autenticado (borrado lógico, reversible
     * por un admin). No borra datos: marca `activo = false`, revoca todos los
     * tokens y cierra la sesión. El historial de reservas/pagos se conserva.
     *
     * Si el usuario es profesional, además cancela TODAS sus reservas activas
     * futuras (pendiente, confirmada, pagada) con el motivo "Profesional no
     * disponible — eliminó su cuenta", restaurando sesiones de paquetes y
     * marcando los pagos completados como reembolsados (para que el operador
     * pueda gestionar la devolución).
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        $usuario = $request->user();

        /** @var list<int> $reservasCanceladasIds */
        $reservasCanceladasIds = [];

        DB::transaction(function () use ($usuario, &$reservasCanceladasIds) {
            if ($usuario->role === UserRole::Professional && $usuario->professionalProfile) {
                $reservasCanceladasIds = $this->cancelarReservasActivasDelProfesional(
                    $usuario->professionalProfile,
                );
            }

            $usuario->activo = false;
            $usuario->save();

            // Revocar todos los tokens: la sesión actual y cualquier otra.
            $usuario->tokens()->delete();
        });

        // Side-effects fuera de la transacción: notificación in-app a cada cliente
        // afectado. Si la cancelación falla por cualquier motivo, no rompemos la
        // desactivación de la cuenta (la cuenta ya quedó cerrada arriba).
        // Se hace una sola query con eager loading para evitar N+1.
        if ($reservasCanceladasIds !== []) {
            $reservasParaNotificar = Booking::with(['client', 'service', 'professionalProfile.user'])
                ->whereIn('id', $reservasCanceladasIds)
                ->get();

            foreach ($reservasParaNotificar as $reserva) {
                try {
                    $this->notificaciones->reservaCanceladaPorProfesionalEliminado($reserva);
                } catch (\Throwable $e) {
                    Log::warning(
                        'No se pudo notificar la cancelación por eliminación de profesional',
                        ['booking_id' => $reserva->id, 'error' => $e->getMessage()],
                    );
                }
            }
        }

        $mensaje = $reservasCanceladasIds === []
            ? 'Tu cuenta fue desactivada. Si querés volver, contactá al administrador para reactivarla.'
            : 'Tu cuenta fue desactivada y se cancelaron '
              . count($reservasCanceladasIds)
              . ' reserva(s) activa(s). Los clientes fueron notificados.';

        return response()->json([
            'message' => $mensaje,
            'reservas_canceladas' => count($reservasCanceladasIds),
        ]);
    }

    /**
     * Cancela las reservas activas futuras del profesional al eliminarse la cuenta.
     *
     * @return list<int> IDs de las reservas canceladas.
     */
    private function cancelarReservasActivasDelProfesional(ProfessionalProfile $perfil): array
    {
        $motivo = 'Profesional no disponible — eliminó su cuenta';

        $reservas = Booking::query()
            ->with(['payment', 'packagePurchase'])
            ->where('professional_profile_id', $perfil->id)
            ->whereIn('estado', [
                BookingStatus::Pendiente->value,
                BookingStatus::Confirmada->value,
                BookingStatus::Pagada->value,
            ])
            ->where('fecha_hora', '>=', Carbon::now())
            ->get();

        $idsCanceladas = [];
        $ahora = Carbon::now();

        foreach ($reservas as $reserva) {
            $reserva->update([
                'estado' => BookingStatus::Cancelada,
                'cancelled_at' => $ahora,
                'cancel_motivo' => $motivo,
                'active_slot' => null,
            ]);

            if ($reserva->payment) {
                if ($reserva->payment->estado === PaymentStatus::Pendiente) {
                    $reserva->payment->update(['estado' => PaymentStatus::Cancelado]);
                } elseif ($reserva->payment->estado === PaymentStatus::Completado) {
                    // El cliente ya pagó — el admin tendrá que gestionar la devolución.
                    $reserva->payment->update(['estado' => PaymentStatus::Reembolsado]);
                }
            }

            if ($reserva->packagePurchase) {
                $reserva->packagePurchase->increment('sesiones_restantes');
            }

            $idsCanceladas[] = $reserva->id;
        }

        return $idsCanceladas;
    }
}
