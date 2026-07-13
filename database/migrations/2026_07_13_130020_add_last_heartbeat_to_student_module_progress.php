<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Traccia l'ultimo heartbeat per modulo: il server accredita solo il tempo
 * realmente trascorso dall'ultimo ping (cap anti-frode), evitando che un client
 * gonfi il tempo con ping ravvicinati.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_module_progress', function (Blueprint $table) {
            // Accumulo preciso al secondo (l'heartbeat accredita ~30s per ping);
            // time_spent_minutes resta derivato per retrocompatibilità.
            $table->integer('tracked_seconds')->default(0)->after('time_spent_minutes');
            $table->timestamp('last_heartbeat_at')->nullable()->after('tracked_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('student_module_progress', function (Blueprint $table) {
            $table->dropColumn('last_heartbeat_at');
        });
    }
};
