<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Drop old unique constraint
            $table->dropUnique('bookings_professional_slot_unique');
            
            // Add active_slot column (nullable dateTime)
            $table->dateTime('active_slot')->nullable()->after('fecha_hora');
        });

        // Populate active_slot for existing non-cancelled bookings
        DB::table('bookings')
            ->where('estado', '!=', 'cancelada')
            ->update(['active_slot' => DB::raw('fecha_hora')]);

        Schema::table('bookings', function (Blueprint $table) {
            // Add new unique index on professional_profile_id + active_slot
            $table->unique(['professional_profile_id', 'active_slot'], 'bookings_professional_slot_unique');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Drop the new unique constraint
            $table->dropUnique('bookings_professional_slot_unique');
        });

        Schema::table('bookings', function (Blueprint $table) {
            // Drop active_slot column
            $table->dropColumn('active_slot');

            // Re-create the old unique constraint
            $table->unique(['professional_profile_id', 'fecha_hora'], 'bookings_professional_slot_unique');
        });
    }
};
