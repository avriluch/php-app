<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\Booking;
use App\Models\Notification;
use App\Models\Payment;
use Carbon\Carbon;

/**
 * Crea las notificaciones in-app de forma SÍNCRONA (no dependen del queue worker).
 *
 * Los Jobs (EnviarConfirmacionReserva, etc.) siguen encargándose del email y del
 * broadcast por WebSocket; acá solo persistimos el registro en `notifications`
 * para que el badge aparezca al instante aunque la cola no esté corriendo.
 */
class NotificacionService
{
    public function reservaCreada(Booking $reserva): void
    {
        $reserva->loadMissing(['client', 'professionalProfile.user', 'service']);

        $cliente = $reserva->client;
        $profUser = $reserva->professionalProfile?->user;
        $fecha = $reserva->fecha_hora?->format('d/m/Y H:i');

        if ($cliente) {
            $this->crear(
                $cliente->id,
                $reserva->id,
                NotificationType::Confirmacion,
                'Tu reserva con ' . ($profUser?->nombre ?? 'tu profesional') . ' fue creada para el ' . $fecha . '.',
            );
        }

        if ($profUser) {
            $this->crear(
                $profUser->id,
                $reserva->id,
                NotificationType::Confirmacion,
                'Nueva reserva de ' . ($cliente?->nombre ?? 'un cliente') . ' para el ' . $fecha . '.',
            );
        }
    }

    public function reservaCancelada(Booking $reserva): void
    {
        $reserva->loadMissing(['client', 'professionalProfile.user']);

        $cliente = $reserva->client;
        $profUser = $reserva->professionalProfile?->user;
        $fecha = $reserva->fecha_hora?->format('d/m/Y H:i');

        if ($cliente) {
            $this->crear(
                $cliente->id,
                $reserva->id,
                NotificationType::Cancelacion,
                'Tu reserva del ' . $fecha . ' fue cancelada.',
            );
        }

        if ($profUser) {
            $this->crear(
                $profUser->id,
                $reserva->id,
                NotificationType::Cancelacion,
                'La reserva de ' . ($cliente?->nombre ?? 'un cliente') . ' del ' . $fecha . ' fue cancelada.',
            );
        }
    }

    /**
     * Cliente recibe aviso de que su reserva fue cancelada porque el profesional
     * eliminó su cuenta. No notificamos al profesional (ya se fue).
     */
    public function reservaCanceladaPorProfesionalEliminado(Booking $reserva): void
    {
        $reserva->loadMissing(['client', 'service', 'professionalProfile.user']);

        $cliente = $reserva->client;
        if (! $cliente) {
            return;
        }

        $fecha = $reserva->fecha_hora?->format('d/m/Y H:i');
        $servicio = $reserva->service?->nombre ?? 'tu reserva';
        $profUser = $reserva->professionalProfile?->user;
        $profNombre = trim(($profUser?->nombre ?? '') . ' ' . ($profUser?->apellido ?? ''));
        $detalle = $profNombre !== '' ? " con {$profNombre}" : '';

        $this->crear(
            $cliente->id,
            $reserva->id,
            NotificationType::Cancelacion,
            "Tu reserva de {$servicio}{$detalle} del {$fecha} fue cancelada: el profesional ya no está disponible.",
        );
    }

    public function reservaReagendada(Booking $reserva, ?string $fechaAnterior = null): void
    {
        $reserva->loadMissing(['client', 'professionalProfile.user']);

        $cliente = $reserva->client;
        $profUser = $reserva->professionalProfile?->user;
        $nuevaFecha = $reserva->fecha_hora?->format('d/m/Y H:i');
        $detalle = $fechaAnterior ? (' (antes ' . $fechaAnterior . ')') : '';

        if ($cliente) {
            $this->crear(
                $cliente->id,
                $reserva->id,
                NotificationType::Reagendacion,
                'Tu reserva fue reprogramada para el ' . $nuevaFecha . $detalle . '.',
            );
        }

        if ($profUser) {
            $this->crear(
                $profUser->id,
                $reserva->id,
                NotificationType::Reagendacion,
                ($cliente?->nombre ?? 'Un cliente') . ' reprogramó su reserva para el ' . $nuevaFecha . $detalle . '.',
            );
        }
    }

    public function pagoCompletado(Payment $payment): void
    {
        $monto = $this->formatoMonto((float) $payment->monto);

        if ($payment->booking) {
            $reserva = $payment->booking->loadMissing(['client', 'professionalProfile.user', 'service']);
            $cliente = $reserva->client;
            $profUser = $reserva->professionalProfile?->user;
            $servicio = $reserva->service?->nombre ?? 'tu servicio';

            if ($cliente) {
                $this->crear(
                    $cliente->id,
                    $reserva->id,
                    NotificationType::Pago,
                    'Tu pago de ' . $monto . ' por ' . $servicio . ' fue confirmado.',
                );
            }

            if ($profUser) {
                $this->crear(
                    $profUser->id,
                    $reserva->id,
                    NotificationType::Pago,
                    ($cliente?->nombre ?? 'Un cliente') . ' pagó la reserva de ' . $servicio . ' (' . $monto . ').',
                );
            }

            return;
        }

        if ($payment->packagePurchase) {
            $compra = $payment->packagePurchase->loadMissing(['client', 'service.professionalProfile.user']);
            $cliente = $compra->client;
            $servicio = $compra->service?->nombre ?? 'el paquete';
            $profUser = $compra->service?->professionalProfile?->user;

            if ($cliente) {
                $this->crear(
                    $cliente->id,
                    null,
                    NotificationType::Pago,
                    'Tu pago del paquete ' . $servicio . ' (' . $monto . ') fue confirmado.',
                );
            }

            if ($profUser) {
                $this->crear(
                    $profUser->id,
                    null,
                    NotificationType::Pago,
                    ($cliente?->nombre ?? 'Un cliente') . ' compró el paquete ' . $servicio . ' (' . $monto . ').',
                );
            }
        }
    }

    private function crear(int $userId, ?int $bookingId, NotificationType $tipo, string $mensaje): void
    {
        Notification::create([
            'user_id' => $userId,
            'booking_id' => $bookingId,
            'tipo' => $tipo,
            'mensaje' => $mensaje,
            'fecha_envio' => Carbon::now(),
        ]);
    }

    private function formatoMonto(float $monto): string
    {
        return '$' . number_format($monto, 0, ',', '.');
    }
}
