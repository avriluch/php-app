<?php

namespace App\Support;

class ProfilePhotoUrl
{
    public static function resolve(?string $foto): ?string
    {
        if (! $foto) {
            return null;
        }

        $foto = trim($foto);

        // Corrige URLs mal formadas guardadas antes (doble host o url() duplicado).
        if (str_contains($foto, '/storage/')) {
            $foto = substr($foto, (int) strpos($foto, '/storage/'));
        }

        if (str_starts_with($foto, 'http://') || str_starts_with($foto, 'https://')) {
            return $foto;
        }

        $path = str_starts_with($foto, '/') ? $foto : '/'.$foto;

        return rtrim(config('app.url'), '/').$path;
    }
}
