@php
    $cliente = $reserva->client;
    $profUser = $reserva->professionalProfile?->user;
    $servicio = $reserva->service;
    $esProfesional = $destinatario === 'profesional';
    $nombreSaludo = $esProfesional ? $profUser?->nombre : $cliente?->nombre;
@endphp

<x-mail::message>
# Reserva reagendada

Hola {{ $nombreSaludo }},

@if ($esProfesional)
La reserva de **{{ $cliente?->nombre }} {{ $cliente?->apellido }}** fue reagendada.
@else
Tu reserva con **{{ $profUser?->nombre }} {{ $profUser?->apellido }}** fue reagendada.
@endif

<x-mail::panel>
**Servicio:** {{ $servicio?->nombre }}
@if ($fechaAnterior)
**Fecha y hora anterior:** {{ $fechaAnterior }}
@endif
**Nueva fecha y hora:** {{ $reserva->fecha_hora?->format('d/m/Y H:i') }}
**Modalidad:** {{ ucfirst($reserva->modalidad->value) }}
**Duración:** {{ $servicio?->duracion }} minutos
**Estado:** {{ ucfirst($reserva->estado->value) }}
</x-mail::panel>

<x-mail::button :url="config('app.frontend_url') . '/dashboard/' . ($esProfesional ? 'professional' : 'client')">
Ver detalles
</x-mail::button>

Gracias por usar {{ config('app.name') }}.
</x-mail::message>
