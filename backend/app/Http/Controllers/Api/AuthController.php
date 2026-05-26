<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\ProfessionalProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
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

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(new UserResource($request->user()));
    }

    public function updateMe(Request $request): JsonResponse
    {
        $usuario = $request->user();

        $datos = $request->validate([
            'nombre' => ['sometimes', 'string', 'max:100'],
            'apellido' => ['sometimes', 'string', 'max:100'],
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

        return response()->json(new UserResource($usuario));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Sesión cerrada.']);
    }
}
