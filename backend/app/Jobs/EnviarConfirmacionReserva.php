<?php

namespace App\Jobs;

use App\Events\NuevaReservaProfesional;
use App\Models\Booking;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use App\Services\BrevoMailService;

/**
 * Envía el email de confirmación al cliente y al profesional, y transmite el
 * broadcast en vivo al profesional. La notificación in-app se crea de forma
 * síncrona en el controller (NotificacionService), no acá.
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

        $brevoMail = app(BrevoMailService::class);

        // La notificación in-app ya se creó de forma síncrona en el controller
        // (NotificacionService). Acá solo enviamos el email y el broadcast.

        // Email al cliente
        if ($cliente) {
            $brevoMail->send(
                $cliente->email,
                'Reserva confirmada con ' . ($profUser?->nombre ?? 'tu profesional'),
                'mail.reserva-creada',
                ['reserva' => $reserva, 'destinatario' => 'cliente'],
            );
        }

        // Email + broadcast al profesional
        if ($profUser) {
            $brevoMail->send(
                $profUser->email,
                'Nueva reserva: ' . ($cliente?->nombre ?? 'Cliente') . ' ' . ($cliente?->apellido ?? ''),
                'mail.reserva-creada',
                ['reserva' => $reserva, 'destinatario' => 'profesional'],
            );

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
