@php
    $cliente = $reserva->client;
    $profUser = $reserva->professionalProfile?->user;
    $servicio = $reserva->service;
    $esProfesional = $destinatario === 'profesional';
    $nombreSaludo = $esProfesional ? $profUser?->nombre : $cliente?->nombre;
@endphp

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reserva cancelada</title>
</head>

<body style="font-family: Arial, sans-serif; background:#f6f6f6; padding:20px;">

<div style="max-width:600px; margin:auto; background:#ffffff; padding:20px; border-radius:10px;">

    <h2 style="color:#dc2626;">
        Reserva cancelada
    </h2>

    <p>Hola <strong>{{ $nombreSaludo }}</strong>,</p>

    @if ($esProfesional)
        <p>
            La reserva de <strong>{{ $cliente?->nombre }} {{ $cliente?->apellido }}</strong>
            fue cancelada.
        </p>
    @else
        <p>
            Tu reserva con <strong>{{ $profUser?->nombre }} {{ $profUser?->apellido }}</strong>
            quedó cancelada.
        </p>
    @endif

    <div style="border:1px solid #ddd; padding:15px; border-radius:8px; margin-top:15px;">

        <p><strong>Servicio:</strong> {{ $servicio?->nombre }}</p>

        <p><strong>Fecha y hora original:</strong>
            {{ $reserva->fecha_hora?->format('d/m/Y H:i') }}
        </p>

        <p><strong>Cancelada el:</strong>
            {{ $reserva->cancelled_at?->format('d/m/Y H:i') }}
        </p>

        @if ($reserva->cancel_motivo)
            <p><strong>Motivo:</strong> {{ $reserva->cancel_motivo }}</p>
        @endif

    </div>

    @if (! $esProfesional)
        <p style="margin-top:15px;">
            Si querés, podés reservar un nuevo turno desde la plataforma.
        </p>
    @endif

    <div style="margin-top:20px;">
        <a href="{{ config('app.frontend_url') }}/professionals"
           style="background:#2563eb; color:#fff; padding:10px 15px; text-decoration:none; border-radius:5px;">
            Buscar profesionales
        </a>
    </div>

    <p style="margin-top:30px; font-size:12px; color:#777;">
        Gracias por usar {{ config('app.name') }}.
    </p>

</div>

</body>
</html>