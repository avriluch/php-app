<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\PlatformSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAvailable
{
    public function handle(Request $request, Closure $next): Response
    {
        $settings = PlatformSetting::current();

        if (! $settings->mantenimiento_activo) {
            return $next($request);
        }

        if ($request->user()?->role === UserRole::Admin) {
            return $next($request);
        }

        if ($request->is('api/auth/login')) {
            return $next($request);
        }

        if ($request->is('api/platform-settings') && $request->isMethod('GET')) {
            return $next($request);
        }

        return response()->json([
            'message' => $settings->mensaje_mantenimiento
                ?: 'La plataforma está en mantenimiento. Intentá más tarde.',
            'mantenimiento' => true,
        ], 503);
    }
}
