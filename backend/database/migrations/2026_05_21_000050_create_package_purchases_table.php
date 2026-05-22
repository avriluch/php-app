<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sesiones_restantes');
            $table->timestamp('purchased_at')->useCurrent();
            $table->timestamps();

            $table->index(['client_user_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_purchases');
    }
};
