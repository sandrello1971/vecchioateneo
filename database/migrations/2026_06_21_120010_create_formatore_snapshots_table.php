<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// P25.3c — Backup del contenuto formatore LIVE (instructor_manual_sections.content_html)
// PRIMA di un'applicazione, per il rollback vero del live (non solo del sorgente).
//
// Snapshot a livello di BATCH (versione): `version` è la NUOVA versione course_sources
// prodotta dall'applicazione; `content_html` è il contenuto PRE-applicazione della
// sezione. Rollback da quella versione → ripristina le sezioni da qui. Contenuto
// rigenerabile/legato al live → cascade sul delete del corso.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formatore_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignUuid('course_source_id')->constrained('course_sources')->cascadeOnDelete();
            $table->string('version', 20); // versione course_sources prodotta dall'applicazione
            $table->foreignUuid('instructor_manual_section_id')->constrained('instructor_manual_sections')->cascadeOnDelete();
            $table->text('content_html'); // contenuto PRE-applicazione (per rollback)
            $table->timestamps();

            $table->index(['course_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formatore_snapshots');
    }
};
