<?php

namespace App\Providers;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // API REST: respuestas sin envoltorio { "data": ... } en Resources sueltos
        JsonResource::withoutWrapping();
    }
}
