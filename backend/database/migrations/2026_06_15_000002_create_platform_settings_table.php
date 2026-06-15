<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_plataforma')->default('ServiConnect');
            $table->string('email_soporte')->nullable();
            $table->text('mensaje_mantenimiento')->nullable();
            $table->boolean('registro_abierto')->default(true);
            $table->boolean('mantenimiento_activo')->default(false);
            $table->unsignedSmallInteger('recordatorio_horas_antes')->default(24);
            $table->unsignedSmallInteger('antelacion_reserva_min_horas')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
