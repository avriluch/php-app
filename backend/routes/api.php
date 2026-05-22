<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AgendaController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\Api\ProfessionalController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'ok']));

Route::prefix('auth')->group(function () {
    Route::get('/google/redirect', [GoogleAuthController::class, 'redirect']);
    Route::get('/google/callback', [GoogleAuthController::class, 'callback']);

    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::get('/professionals', [ProfessionalController::class, 'index']);
Route::get('/professionals/{id}', [ProfessionalController::class, 'show'])->whereNumber('id');
Route::get('/professionals/{id}/services', [ProfessionalController::class, 'services'])->whereNumber('id');
Route::get('/professionals/{id}/availability', [ProfessionalController::class, 'availability'])->whereNumber('id');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::get('/bookings/{id}', [BookingController::class, 'show'])->whereNumber('id');
    Route::patch('/bookings/{id}/cancel', [BookingController::class, 'cancel'])->whereNumber('id');
    Route::patch('/bookings/{id}/reschedule', [BookingController::class, 'reschedule'])->whereNumber('id');
    Route::patch('/bookings/{id}/status', [BookingController::class, 'updateStatus'])
        ->middleware('role:professional,admin')
        ->whereNumber('id');

    Route::prefix('professional')->middleware('role:professional')->group(function () {
        Route::get('/agenda', [AgendaController::class, 'show']);
        Route::put('/agenda', [AgendaController::class, 'update']);
        Route::post('/agenda/exceptions', [AgendaController::class, 'storeException']);
        Route::delete('/agenda/exceptions/{id}', [AgendaController::class, 'destroyException'])->whereNumber('id');
    });

    Route::prefix('admin')->middleware('role:admin')->group(function () {
        Route::get('/users', [AdminController::class, 'users']);
        Route::get('/metrics', [AdminController::class, 'metrics']);
    });
});
