<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', fn () => response()->json([
    'app' => config('app.name'),
    'api' => url('/api/health'),
]));

// Sirve avatares y otros archivos del disco public cuando falta el symlink (común en deploy).
Route::get('/storage/{path}', function (string $path) {
    $path = str_replace(['..', '\\'], '', $path);

    if ($path === '' || ! Storage::disk('public')->exists($path)) {
        abort(404);
    }

    return response()->file(Storage::disk('public')->path($path));
})->where('path', '.*');
