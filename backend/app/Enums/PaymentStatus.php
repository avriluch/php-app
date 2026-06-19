<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pendiente = 'pendiente';
    case Completado = 'completado';
    case Fallido = 'fallido';
    case Reembolsado = 'reembolsado';
    case Cancelado = 'cancelado';
}
