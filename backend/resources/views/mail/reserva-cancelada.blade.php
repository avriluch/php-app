@php
    $cliente = $reserva->client;
    $profUser = $reserva->professionalProfile?->user;
    $servicio = $reserva->service;
    $esProfesional = $destinatario === 'profesional';
    $nombreSaludo = $esProfesional ? $profUser?->nombre : $cliente?->nombre;
@endphp

<x-mail::message>
# Reserva cancelada

Hola {{ $nombreSaludo }},

@if ($esProfesional)
La reserva de **{{ $cliente?->nombre }} {{ $cliente?->apellido }}** fue cancelada.
@else
Tu reserva con **{{ $profUser?->nombre }} {{ $profUser?->apellido }}** quedó cancelada.
@endif

<x-mail::panel>
**Servicio:** {{ $servicio?->nombre }}
**Fecha y hora original:** {{ $reserva->fecha_hora?->format('d/m/Y H:i') }}
**Cancelada el:** {{ $reserva->cancelled_at?->format('d/m/Y H:i') }}
@if ($reserva->cancel_motivo)
**Motivo:** {{ $reserva->cancel_motivo }}
@endif
</x-mail::panel>

@if (! $esProfesional)
Si querés, podés reservar un nuevo turno desde la plataforma.
@endif

<x-mail::button :url="config('app.frontend_url') . '/professionals'">
Buscar profesionales
</x-mail::button>

Gracias por usar {{ config('app.name') }}.
</x-mail::message>
