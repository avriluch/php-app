<?php

namespace App\Jobs;

use App\Enums\NotificationType;
use App\Events\NuevaReservaProfesional;
use App\Mail\ReservaCreadaMail;
use App\Models\Booking;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Notifica al cliente y al profesional sobre una reserva recién creada.
 * Persiste registros en `notifications` y envía email a ambos.
 */
class EnviarConfirmacionReserva implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public int $reservaId)
    {
    }

    public function handle(): void
    {
        $reserva = Booking::with([
            'client',
            'professionalProfile.user',
            'service',
            'payment',
        ])->find($this->reservaId);

        if (! $reserva) {
            return;
        }

        $profUser = $reserva->professionalProfile?->user;
        $cliente = $reserva->client;
        $fechaFormateada = $reserva->fecha_hora?->format('d/m/Y H:i');

        // Notificación + email al cliente
        if ($cliente) {
            Notification::create([
                'user_id' => $cliente->id,
                'booking_id' => $reserva->id,
                'tipo' => NotificationType::Confirmacion,
                'mensaje' => 'Tu reserva con ' . ($profUser?->nombre ?? 'tu profesional')
                    . ' fue creada para el ' . $fechaFormateada . '.',
                'fecha_envio' => Carbon::now(),
            ]);

            Mail::to($cliente->email)->send(new ReservaCreadaMail($reserva, 'cliente'));
        }

        // Notificación + email al profesional
        if ($profUser) {
            Notification::create([
                'user_id' => $profUser->id,
                'booking_id' => $reserva->id,
                'tipo' => NotificationType::Confirmacion,
                'mensaje' => 'Nueva reserva de ' . ($cliente?->nombre ?? 'un cliente')
                    . ' para el ' . $fechaFormateada . '.',
                'fecha_envio' => Carbon::now(),
            ]);

            Mail::to($profUser->email)->send(new ReservaCreadaMail($reserva, 'profesional'));

            // Transmite en vivo al canal privado del profesional (WebSocket vía Reverb).
            // Best-effort: si Reverb no está disponible, no debe romper el job
            // (los emails y notificaciones ya se enviaron; reintentarlo los duplicaría).
            try {
                NuevaReservaProfesional::dispatch($reserva);
            } catch (\Throwable $e) {
                Log::warning('No se pudo transmitir NuevaReservaProfesional: ' . $e->getMessage());
            }
        }
    }
}
