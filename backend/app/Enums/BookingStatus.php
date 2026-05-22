<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Pendiente = 'pendiente';
    case Confirmada = 'confirmada';
    case Pagada = 'pagada';
    case EnCurso = 'en_curso';
    case Finalizada = 'finalizada';
    case Cancelada = 'cancelada';
    case NoAsistida = 'no_asistida';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pendiente => [self::Confirmada, self::Cancelada],
            self::Confirmada => [self::Pagada, self::Cancelada],
            self::Pagada => [self::EnCurso, self::Cancelada],
            self::EnCurso => [self::Finalizada, self::NoAsistida],
            default => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }
}
