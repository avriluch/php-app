<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse|Response
    {
        if (! config('services.google.client_id')) {
            return response()->json([
                'message' => 'Google OAuth no está configurado. Agregá GOOGLE_CLIENT_ID y GOOGLE_CLIENT_SECRET en backend/.env',
                'docs' => 'https://console.cloud.google.com/apis/credentials',
            ], 503);
        }

        return Socialite::driver('google')
            ->stateless()
            ->redirect();
    }

    public function callback(): RedirectResponse|Response
    {
        if (! config('services.google.client_id')) {
            return $this->redirectToFrontendWithError('OAuth no configurado en el servidor.');
        }

        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (\Throwable $e) {
            return $this->redirectToFrontendWithError('No se pudo completar el inicio con Google.');
        }

        $parts = $this->splitName($googleUser->getName() ?? 'Usuario Google');

        $user = User::query()
            ->where('google_id', $googleUser->getId())
            ->orWhere('email', $googleUser->getEmail())
            ->first();

        if ($user) {
            $user->update([
                'google_id' => $googleUser->getId(),
                'foto_perfil' => $user->foto_perfil ?? $googleUser->getAvatar(),
            ]);
        } else {
            $user = User::create([
                'google_id' => $googleUser->getId(),
                'nombre' => $parts['nombre'],
                'apellido' => $parts['apellido'],
                'email' => $googleUser->getEmail(),
                'password' => Hash::make(Str::random(40)),
                'foto_perfil' => $googleUser->getAvatar(),
                'role' => UserRole::Client,
            ]);
        }

        $token = $user->createToken('api')->plainTextToken;

        $frontend = rtrim(config('app.frontend_url', 'http://localhost:5173'), '/');

        return redirect($frontend.'/auth/oauth-callback?token='.urlencode($token));
    }

    private function redirectToFrontendWithError(string $message): RedirectResponse
    {
        $frontend = rtrim(config('app.frontend_url', 'http://localhost:5173'), '/');

        return redirect($frontend.'/auth/oauth-callback?error='.urlencode($message));
    }

    /** @return array{nombre: string, apellido: string} */
    private function splitName(string $fullName): array
    {
        $fullName = trim($fullName);
        $space = strpos($fullName, ' ');

        if ($space === false) {
            return ['nombre' => $fullName, 'apellido' => ''];
        }

        return [
            'nombre' => substr($fullName, 0, $space),
            'apellido' => trim(substr($fullName, $space + 1)) ?: '',
        ];
    }
}
