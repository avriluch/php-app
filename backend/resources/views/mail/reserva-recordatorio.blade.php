@php
    $cliente = $reserva->client;
    $profUser = $reserva->professionalProfile?->user;
    $servicio = $reserva->service;
@endphp

<x-mail::message>
# Recordatorio de tu reserva

Hola {{ $cliente?->nombre }},

Te recordamos que tenés una sesión próxima en {{ config('app.name') }}.

<x-mail::panel>
**Profesional:** {{ $profUser?->nombre }} {{ $profUser?->apellido }}
**Servicio:** {{ $servicio?->nombre }}
**Fecha y hora:** {{ $reserva->fecha_hora?->format('d/m/Y H:i') }}
**Modalidad:** {{ ucfirst($reserva->modalidad->value) }}
**Duración:** {{ $servicio?->duracion }} minutos
</x-mail::panel>

@if ($reserva->modalidad->value === 'virtual' && $reserva->url_video_llamada)
**Enlace de videollamada:** {{ $reserva->url_video_llamada }}
@endif

<x-mail::button :url="config('app.frontend_url') . '/dashboard/client/bookings'">
Ver mi reserva
</x-mail::button>

¡Te esperamos!
</x-mail::message>
