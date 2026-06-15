<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Pasarela simulada para débito/crédito (sin datos reales ni PCI).
 * Útil en desarrollo y demos académicas.
 */
class SimulatedCardPaymentService
{
    /** Número de prueba que simula rechazo (estilo Stripe). */
    private const CARD_DECLINED = '4000000000000002';

    /**
     * @param  array{metodo: string, numero: string, titular: string, vencimiento: string, cvv: string}  $datos
     * @return array{referencia: string, metodo: PaymentMethod, ultimos_cuatro: string}
     */
    public function authorize(array $datos): array
    {
        $metodo = PaymentMethod::from($datos['metodo']);
        $numero = preg_replace('/\D/', '', $datos['numero']) ?? '';
        $cvv = preg_replace('/\D/', '', $datos['cvv']) ?? '';
        $titular = trim($datos['titular']);
        $vencimiento = trim($datos['vencimiento']);

        $this->validar($numero, $cvv, $titular, $vencimiento);

        if ($numero === self::CARD_DECLINED || str_ends_with($numero, '0000')) {
            throw ValidationException::withMessages([
                'numero' => 'La tarjeta fue rechazada por el emisor (simulado).',
            ]);
        }

        return [
            'referencia' => 'sim-' . Str::uuid()->toString(),
            'metodo' => $metodo,
            'ultimos_cuatro' => substr($numero, -4),
        ];
    }

    private function validar(string $numero, string $cvv, string $titular, string $vencimiento): void
    {
        $errores = [];

        if (strlen($numero) < 13 || strlen($numero) > 19) {
            $errores['numero'] = 'El número de tarjeta no es válido.';
        } elseif (! $this->luhnValido($numero)) {
            $errores['numero'] = 'El número de tarjeta no es válido.';
        }

        if (strlen($titular) < 3) {
            $errores['titular'] = 'Ingresá el nombre del titular.';
        }

        if (! preg_match('/^(0[1-9]|1[0-2])\/?([0-9]{2}|[0-9]{4})$/', $vencimiento)) {
            $errores['vencimiento'] = 'Usá el formato MM/AA o MM/AAAA.';
        } elseif (! $this->vencimientoFuturo($vencimiento)) {
            $errores['vencimiento'] = 'La tarjeta está vencida.';
        }

        if (strlen($cvv) < 3 || strlen($cvv) > 4) {
            $errores['cvv'] = 'El CVV debe tener 3 o 4 dígitos.';
        }

        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }
    }

    private function luhnValido(string $numero): bool
    {
        $suma = 0;
        $alternar = false;

        for ($i = strlen($numero) - 1; $i >= 0; $i--) {
            $digito = (int) $numero[$i];
            if ($alternar) {
                $digito *= 2;
                if ($digito > 9) {
                    $digito -= 9;
                }
            }
            $suma += $digito;
            $alternar = ! $alternar;
        }

        return $suma % 10 === 0;
    }

    private function vencimientoFuturo(string $vencimiento): bool
    {
        if (! preg_match('/^(0[1-9]|1[0-2])\/?([0-9]{2}|[0-9]{4})$/', $vencimiento, $m)) {
            return false;
        }

        $mes = (int) $m[1];
        $anio = (int) $m[2];
        if ($anio < 100) {
            $anio += 2000;
        }

        $vence = Carbon::create($anio, $mes, 1)->endOfMonth()->endOfDay();

        return $vence->isFuture();
    }
}
