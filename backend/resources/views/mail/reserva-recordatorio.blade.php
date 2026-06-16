@php
    $cliente = $reserva->client;
    $profUser = $reserva->professionalProfile?->user;
    $servicio = $reserva->service;
@endphp

<div style="font-family: Arial, sans-serif; color:#333; line-height:1.5;">

    <h2 style="color:#16A34A;">
        Recordatorio de tu reserva
    </h2>

    <p>Hola {{ $cliente?->nombre }},</p>

    <p>
        Te recordamos que tenés una sesión próxima en
        <strong>{{ config('app.name') }}</strong>.
    </p>

    <!-- PANEL -->
    <div style="border:1px solid #e5e5e5; border-radius:8px; padding:15px; margin:20px 0; background:#f9f9f9;">

        <p>
            <strong>Profesional:</strong>
            {{ $profUser?->nombre }} {{ $profUser?->apellido }}
        </p>

        <p>
            <strong>Servicio:</strong>
            {{ $servicio?->nombre }}
        </p>

        <p>
            <strong>Fecha y hora:</strong>
            {{ $reserva->fecha_hora?->format('d/m/Y H:i') }}
        </p>

        <p>
            <strong>Modalidad:</strong>
            {{ ucfirst($reserva->modalidad->value) }}
        </p>

        <p>
            <strong>Duración:</strong>
            {{ $servicio?->duracion }} minutos
        </p>

        @if ($reserva->modalidad->value === 'virtual' && $reserva->url_video_llamada)
            <p>
                <strong>Enlace de videollamada:</strong><br>
                <a href="{{ $reserva->url_video_llamada }}" style="color:#16A34A;">
                    {{ $reserva->url_video_llamada }}
                </a>
            </p>
        @endif

    </div>

    <!-- BOTÓN -->
    <div style="margin:30px 0;">
        <a href="{{ config('app.frontend_url') . '/dashboard/client/bookings' }}"
           style="
                background:#16A34A;
                color:white;
                padding:12px 20px;
                text-decoration:none;
                border-radius:6px;
                display:inline-block;
           ">
            Ver mi reserva
        </a>
    </div>

    <p style="margin-top:30px;">
        ¡Te esperamos!
    </p>

</div>