<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\PlatformSetting;
use App\Models\ProfessionalProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
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
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        $usuario = $request->user();

        $usuario->activo = false;
        $usuario->save();

        // Revocar todos los tokens: la sesión actual y cualquier otra.
        $usuario->tokens()->delete();

        return response()->json([
            'message' => 'Tu cuenta fue desactivada. Si querés volver, contactá al administrador para reactivarla.',
        ]);
    }
}
