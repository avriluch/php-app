<?php

namespace App\Enums;

enum NotificationType: string
{
    case Confirmacion = 'confirmacion';
    case Recordatorio = 'recordatorio';
    case Cancelacion = 'cancelacion';
}
