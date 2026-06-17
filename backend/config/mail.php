<?php

return [

    'default' => env('MAIL_MAILER', 'log'),

    'mailers' => [

        'log' => [
            'transport' => 'log',
        ],

    ],

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        // Railway no expande "${APP_NAME}"; si queda literal, usamos APP_NAME de Laravel.
        'name' => match (true) {
            blank($name = env('MAIL_FROM_NAME')) => config('app.name'),
            str_contains($name, '${') => config('app.name'),
            default => $name,
        },
    ],

];