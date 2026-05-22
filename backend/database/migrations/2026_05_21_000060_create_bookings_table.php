<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('professional_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_purchase_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('fecha_hora');
            $table->enum('modalidad', ['virtual', 'presencial', 'hibrida']);
            $table->enum('estado', [
                'pendiente',
                'confirmada',
                'pagada',
                'en_curso',
                'finalizada',
                'cancelada',
                'no_asistida',
            ])->default('pendiente');
            $table->string('url_video_llamada')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_motivo')->nullable();
            $table->timestamps();

            // Control de concurrencia: un slot por profesional
            $table->unique(['professional_profile_id', 'fecha_hora'], 'bookings_professional_slot_unique');
            $table->index(['client_user_id', 'estado']);
            $table->index(['professional_profile_id', 'estado', 'fecha_hora']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
