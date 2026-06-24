<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// P25.B-a.1 — Toggle separato per le proposte sul materiale STUDENTE. Default FALSE
// (opt-in esplicito per corso): l'agente non propone sul contenuto utente-finale finché
// non viene attivato. `proposals_enabled` resta per il lato formatore. Additiva.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_freshness_configs', function (Blueprint $table) {
            $table->boolean('student_proposals_enabled')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('course_freshness_configs', function (Blueprint $table) {
            $table->dropColumn('student_proposals_enabled');
        });
    }
};
