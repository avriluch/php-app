@php
    $cliente = $reserva->client;
    $profUser = $reserva->professionalProfile?->user;
    $servicio = $reserva->service;
    $esProfesional = $destinatario === 'profesional';
    $nombreSaludo = $esProfesional ? $profUser?->nombre : $cliente?->nombre;
@endphp

<div style="font-family: Arial, sans-serif; color:#333; line-height:1.5;">

    <h2 style="color:#16A34A;">
        {{ $esProfesional ? 'Nueva reserva' : '¡Reserva creada!' }}
    </h2>

    <p>Hola {{ $nombreSaludo }},</p>

    @if ($esProfesional)
        <p>
            <strong>{{ $cliente?->nombre }} {{ $cliente?->apellido }}</strong>
            reservó una sesión contigo.
        </p>
    @else
        <p>
            Te confirmamos que tu reserva con
            <strong>{{ $profUser?->nombre }} {{ $profUser?->apellido }}</strong>
            quedó registrada.
        </p>
    @endif

    <!-- PANEL -->
    <div style="border:1px solid #e5e5e5; border-radius:8px; padding:15px; margin:20px 0; background:#f9f9f9;">

        <p><strong>Servicio:</strong> {{ $servicio?->nombre }}</p>

        <p><strong>Fecha y hora:</strong>
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

    @if (! $esProfesional && $reserva->payment && $reserva->payment->estado->value === 'pendiente')
        <p style="color:#b45309;">
            Tu pago de <strong>
                ${{ number_format((float) $reserva->payment->monto, 2, ',', '.') }}
            </strong>
            está pendiente. Podés completarlo desde el panel.
        </p>
    @endif

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