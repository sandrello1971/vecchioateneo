<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// P25.3d — Cadenza dello scheduler per corso + ultimo run.
// Default 'off' (OPT-IN): lo scheduler non tocca un corso finché un admin non ne
// imposta esplicitamente la cadenza. Scelta CONSERVATIVA sul costo — la Fase 2 usa
// Opus 4.8 + web_search (1 chiamata/claim), quindi niente "tutti i corsi ogni settimana"
// per default. L'admin abilita weekly/monthly/quarterly dove serve.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_freshness_configs', function (Blueprint $table) {
            $table->string('cadence', 12)->default('off')->after('proposals_enabled');
            $table->timestamp('last_run_at')->nullable()->after('cadence');
        });

        DB::statement("ALTER TABLE course_freshness_configs ADD CONSTRAINT course_freshness_configs_cadence_check
            CHECK (cadence IN ('off', 'weekly', 'monthly', 'quarterly'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE course_freshness_configs DROP CONSTRAINT IF EXISTS course_freshness_configs_cadence_check');
        Schema::table('course_freshness_configs', function (Blueprint $table) {
            $table->dropColumn(['cadence', 'last_run_at']);
        });
    }
};
