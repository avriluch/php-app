<?php

namespace App\Enums;

enum Modalidad: string
{
    case Virtual = 'virtual';
    case Presencial = 'presencial';
    case Hibrida = 'hibrida';
}
