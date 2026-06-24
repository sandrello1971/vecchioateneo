<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// P25.1 — Sorgente strutturato versionato di un corso (Course Freshness Agent).
// `blocks` jsonb è il SORGENTE DI VERITÀ del contenuto (blocchi tipizzati:
// PART/H1/H2/P/BOX/EX/ESE/NUM/BUL). Il PDF è un OUTPUT rigenerato dai blocchi.
//
// REGOLA CRITICA (spec §1.2): l'aggancio è SEMPRE per `course_id` interno, MAI per
// nome. La FK su `courses(id)` lo garantisce a livello di schema: si può inserire
// solo un uuid di corso esistente, mai un'etichetta.
//
// Immutabile e versionato: una nuova versione = una NUOVA riga (niente updated_at).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_sources', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('version', 20); // es. "1.0" — fornita esplicitamente dal recupero/rigenerazione
            $table->jsonb('blocks');
            $table->timestamp('created_at')->useCurrent();

            // Una sola riga per (corso, versione). Indicizza anche il lookup per corso.
            $table->unique(['course_id', 'version'], 'course_sources_course_version_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_sources');
    }
};
