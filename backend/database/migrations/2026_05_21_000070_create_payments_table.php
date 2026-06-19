<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('package_purchase_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->decimal('monto', 10, 2);
            $table->enum('estado', ['pendiente', 'completado', 'fallido', 'reembolsado', 'cancelado'])->default('pendiente');
            $table->enum('metodo', ['tarjeta_debito', 'tarjeta_credito', 'paypal'])->nullable();
            $table->timestamp('fecha_pago')->nullable();
            $table->string('referencia_pasarela')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
