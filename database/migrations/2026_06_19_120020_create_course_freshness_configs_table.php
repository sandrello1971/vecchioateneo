<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// P25.2 — Configurazione dell'agente per corso (spec §2 Fase 2):
// - `web_search_enabled`: la ricerca web è disattivabile per corso (es. corsi di
//   conformità in cui si vogliono solo fonti ancorate).
// - `primary_sources`: fonti primarie ancorate preferite (URL/domini), valutate prima
//   del fallback di ricerca web.
// Una riga per corso (unique). Default: ricerca attiva, nessuna fonte ancorata.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_freshness_configs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('course_id')->constrained('courses')->cascadeOnDelete();
            $table->boolean('web_search_enabled')->default(true);
            $table->jsonb('primary_sources')->default('[]');
            $table->timestamps();

            $table->unique('course_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_freshness_configs');
    }
};
