<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// P25.3 — Estende la config per corso con:
// - `audience` (adult|minor): pilota il gate Schola/minori (P25.3e). Default 'adult'.
//   Popolamento: euristica sul nome corso (Licei/Istituti/scuole → minor) come
//   suggerimento iniziale + OVERRIDE manuale dall'admin (autorevole). NB: i `courses`
//   non hanno legame strutturale con le scuole, quindi il marcatore vive qui.
// - `proposals_enabled` (default true): permette di far girare l'agente in modalità
//   SOLO-claim (Fase 1-2) senza generare proposte (Fase 3 disattivabile per corso).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_freshness_configs', function (Blueprint $table) {
            $table->string('audience', 12)->default('adult')->after('primary_sources');
            $table->boolean('proposals_enabled')->default(true)->after('audience');
        });

        DB::statement("ALTER TABLE course_freshness_configs ADD CONSTRAINT course_freshness_configs_audience_check
            CHECK (audience IN ('adult', 'minor'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE course_freshness_configs DROP CONSTRAINT IF EXISTS course_freshness_configs_audience_check');
        Schema::table('course_freshness_configs', function (Blueprint $table) {
            $table->dropColumn(['audience', 'proposals_enabled']);
        });
    }
};
