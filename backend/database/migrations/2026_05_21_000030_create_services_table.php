<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('professional_profile_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['session', 'package']);
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->unsignedSmallInteger('duracion')->nullable()->comment('Minutos; null en paquetes si varía');
            $table->decimal('precio', 10, 2);
            $table->enum('modalidad', ['virtual', 'presencial', 'hibrida']);
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('cantidad_sesiones')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['professional_profile_id', 'type', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
