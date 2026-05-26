@php
    $cliente = $reserva->client;
    $profUser = $reserva->professionalProfile?->user;
    $servicio = $reserva->service;
    $esProfesional = $destinatario === 'profesional';
    $nombreSaludo = $esProfesional ? $profUser?->nombre : $cliente?->nombre;
@endphp

<x-mail::message>
# {{ $esProfesional ? 'Nueva reserva' : '¡Reserva creada!' }}

Hola {{ $nombreSaludo }},

@if ($esProfesional)
**{{ $cliente?->nombre }} {{ $cliente?->apellido }}** reservó una sesión contigo.
@else
Te confirmamos que tu reserva con **{{ $profUser?->nombre }} {{ $profUser?->apellido }}** quedó registrada.
@endif

<x-mail::panel>
**Servicio:** {{ $servicio?->nombre }}
**Fecha y hora:** {{ $reserva->fecha_hora?->format('d/m/Y H:i') }}
**Modalidad:** {{ ucfirst($reserva->modalidad->value) }}
**Duración:** {{ $servicio?->duracion }} minutos
**Estado:** {{ ucfirst($reserva->estado->value) }}
</x-mail::panel>

@if (! $esProfesional && $reserva->payment && $reserva->payment->estado->value === 'pendiente')
Tu pago de **\${{ number_format((float) $reserva->payment->monto, 2, ',', '.') }}** está pendiente. Podés completarlo desde el panel.
@endif

<x-mail::button :url="config('app.frontend_url') . '/dashboard/' . ($esProfesional ? 'professional' : 'client')">
Ver detalles
</x-mail::button>

Gracias por usar {{ config('app.name') }}.
</x-mail::message>
