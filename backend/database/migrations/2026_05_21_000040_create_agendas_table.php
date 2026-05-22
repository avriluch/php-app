<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agendas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('professional_profile_id')->unique()->constrained()->cascadeOnDelete();
            $table->time('horario_inicio');
            $table->time('horario_fin');
            $table->json('dias_disponibles')->comment('0=domingo … 6=sábado');
            $table->unsignedSmallInteger('buffer_minutos')->default(0);
            $table->timestamps();
        });

        Schema::create('agenda_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agenda_id')->constrained()->cascadeOnDelete();
            $table->date('fecha');
            $table->string('motivo');
            $table->timestamps();

            $table->unique(['agenda_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_exceptions');
        Schema::dropIfExists('agendas');
    }
};
