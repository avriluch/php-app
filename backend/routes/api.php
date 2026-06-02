<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AgendaController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PackagePurchaseController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProfessionalController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\ServiceController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'ok']));

Route::prefix('auth')->group(function () {
    Route::get('/google/redirect', [GoogleAuthController::class, 'redirect']);
    Route::get('/google/callback', [GoogleAuthController::class, 'callback']);

    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::patch('/me', [AuthController::class, 'updateMe']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

// ── Públicos ──────────────────────────────────────────────────────
Route::get('/professionals', [ProfessionalController::class, 'index']);
Route::get('/professionals/{id}', [ProfessionalController::class, 'show'])->whereNumber('id');
Route::get('/professionals/{id}/services', [ProfessionalController::class, 'services'])->whereNumber('id');
Route::get('/professionals/{id}/availability', [ProfessionalController::class, 'availability'])->whereNumber('id');
Route::get('/professionals/{id}/reviews', [ReviewController::class, 'porProfesional'])->whereNumber('id');

// ── Autenticados ──────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    // Autenticación de canales privados (Reverb / Laravel Echo).
    // El frontend hace POST aquí con el Bearer token cuando se suscribe a un canal.
    Route::post('/broadcasting/auth', fn () => Broadcast::auth(request()));

    // Sesión actual
    Route::get('/me/stats', [MeController::class, 'stats']);

    // Reservas
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::get('/bookings/{id}', [BookingController::class, 'show'])->whereNumber('id');
    Route::patch('/bookings/{id}/cancel', [BookingController::class, 'cancel'])->whereNumber('id');
    Route::patch('/bookings/{id}/reschedule', [BookingController::class, 'reschedule'])->whereNumber('id');
    Route::patch('/bookings/{id}/status', [BookingController::class, 'updateStatus'])
        ->middleware('role:professional,admin')
        ->whereNumber('id');

    // Pagos PayPal
    Route::middleware('role:client')->group(function () {
        Route::post('/payments/{id}/paypal/create-order', [PaymentController::class, 'createPayPalOrder'])->whereNumber('id');
        Route::post('/payments/{id}/paypal/capture', [PaymentController::class, 'capturePayPalOrder'])->whereNumber('id');
    });

    // Reseñas
    Route::post('/bookings/{id}/review', [ReviewController::class, 'store'])->whereNumber('id');
    Route::get('/reviews/mine', [ReviewController::class, 'mias'])->middleware('role:client');

    // Notificaciones
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markRead'])->whereNumber('id');
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead']);

    // Paquetes (cliente)
    Route::middleware('role:client')->group(function () {
        Route::get('/package-purchases', [PackagePurchaseController::class, 'index']);
        Route::post('/package-purchases', [PackagePurchaseController::class, 'store']);
    });

    // Profesional: agenda + CRUD servicios
    Route::prefix('professional')->middleware('role:professional')->group(function () {
        Route::get('/agenda', [AgendaController::class, 'show']);
        Route::put('/agenda', [AgendaController::class, 'update']);
        Route::post('/agenda/exceptions', [AgendaController::class, 'storeException']);
        Route::delete('/agenda/exceptions/{id}', [AgendaController::class, 'destroyException'])->whereNumber('id');

        Route::get('/services', [ServiceController::class, 'index']);
        Route::post('/services', [ServiceController::class, 'store']);
        Route::patch('/services/{id}', [ServiceController::class, 'update'])->whereNumber('id');
        Route::delete('/services/{id}', [ServiceController::class, 'destroy'])->whereNumber('id');

        Route::get('/locations', [LocationController::class, 'index']);
        Route::post('/locations', [LocationController::class, 'store']);
    });

    // Admin
    Route::prefix('admin')->middleware('role:admin')->group(function () {
        Route::get('/users', [AdminController::class, 'users']);
        Route::get('/metrics', [AdminController::class, 'metrics']);
    });
});
