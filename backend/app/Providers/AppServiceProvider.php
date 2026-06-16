<?php

namespace App\Providers;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use App\Mail\Transport\BrevoTransport;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // API REST: sin wrapper
        JsonResource::withoutWrapping();

        // BREVO MAIL TRANSPORT
        Mail::extend('brevo', function () {
            return new BrevoTransport(env('BREVO_API_KEY'));
        });
    }
}