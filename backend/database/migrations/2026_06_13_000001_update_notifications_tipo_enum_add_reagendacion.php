<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // `MODIFY ... ENUM` es sintaxis exclusiva de MySQL. En otros drivers (p. ej.
        // sqlite usado en los tests) el enum se mapea a varchar y no requiere el ALTER.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE notifications MODIFY tipo ENUM('confirmacion','recordatorio','cancelacion','reagendacion') NOT NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE notifications MODIFY tipo ENUM('confirmacion','recordatorio','cancelacion') NOT NULL");
    }
};
