<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Recordatorios T+24h: cada hora barre las reservas que entran en la ventana.
Schedule::command('bookings:enviar-recordatorios')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();
