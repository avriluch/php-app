<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agendas', function (Blueprint $table) {
            $table->unsignedSmallInteger('pausa_entre_sesiones_minutos')
                ->default(0)
                ->after('buffer_minutos');
        });
    }

    public function down(): void
    {
        Schema::table('agendas', function (Blueprint $table) {
            $table->dropColumn('pausa_entre_sesiones_minutos');
        });
    }
};
