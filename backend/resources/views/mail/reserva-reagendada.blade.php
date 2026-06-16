@php
    $cliente = $reserva->client;
    $profUser = $reserva->professionalProfile?->user;
    $servicio = $reserva->service;
    $esProfesional = $destinatario === 'profesional';
    $nombreSaludo = $esProfesional ? $profUser?->nombre : $cliente?->nombre;
@endphp

<div style="font-family: Arial, sans-serif; color:#333; line-height:1.5;">

    <h2 style="color:#16A34A;">
        Reserva reagendada
    </h2>

    <p>Hola {{ $nombreSaludo }},</p>

    @if ($esProfesional)
        <p>
            La reserva de
            <strong>{{ $cliente?->nombre }} {{ $cliente?->apellido }}</strong>
            fue reagendada.
        </p>
    @else
        <p>
            Tu reserva con
            <strong>{{ $profUser?->nombre }} {{ $profUser?->apellido }}</strong>
            fue reagendada.
        </p>
    @endif

    <!-- PANEL -->
    <div style="border:1px solid #e5e5e5; border-radius:8px; padding:15px; margin:20px 0; background:#f9f9f9;">

        <p><strong>Servicio:</strong> {{ $servicio?->nombre }}</p>

        @if ($fechaAnterior)
            <p><strong>Fecha y hora anterior:</strong> {{ $fechaAnterior }}</p>
        @endif

        <p><strong>Nueva fecha y hora:</strong>
            {{ $reserva->fecha_hora?->format('d/m/Y H:i') }}
        </p>

        <p><strong>Modalidad:</strong>
            {{ ucfirst($reserva->modalidad->value) }}
        </p>

        <p><strong>Duración:</strong>
            {{ $servicio?->duracion }} minutos
        </p>

        <p><strong>Estado:</strong>
            {{ ucfirst($reserva->estado->value) }}
        </p>

    </div>

    <!-- BOTÓN -->
    <div style="margin:30px 0;">
        <a href="{{ config('app.frontend_url') . '/dashboard/' . ($esProfesional ? 'professional' : 'client') }}"
           style="
                background:#16A34A;
                color:white;
                padding:12px 20px;
                text-decoration:none;
                border-radius:6px;
                display:inline-block;
           ">
            Ver detalles
        </a>
    </div>

    <p style="margin-top:30px;">
        Gracias por usar <strong>{{ config('app.name') }}</strong>.
    </p>

</div>