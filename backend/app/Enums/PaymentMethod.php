<?php

namespace App\Enums;

/**
 * Métodos de pago aceptados. El UML define `Tarjeta de debito` y
 * `Tarjeta de credito`; se agrega `paypal` porque el electivo
 * "Integración con pasarela de pago (PayPal)" lo incorpora.
 */
enum PaymentMethod: string
{
    case TarjetaDebito = 'tarjeta_debito';
    case TarjetaCredito = 'tarjeta_credito';
    case Paypal = 'paypal';
}
