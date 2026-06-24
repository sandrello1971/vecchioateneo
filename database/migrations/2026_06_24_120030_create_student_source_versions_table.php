<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// P25.B-a.1 — Sorgente studente VERSIONATO. Per lo studente sorgente == live
// (modules.content è insieme sorgente e contenuto fruito), quindi UNA sola tabella fa
// da equivalente di `course_sources` E da backup per il rollback: ogni riga è la copia
// COMPLETA del contenuto studente a quella versione. La versione precedente resta
// intatta → rollback. Immutabile (solo created_at), come course_sources.
//
// `content` = [{ "module_id": "...", "content_html": "..." }, ...]
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_source_versions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('version', 20); // es. "1.0" → "1.1" (stringa, mai float)
            $table->jsonb('content');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['course_id', 'version'], 'student_source_versions_course_version_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_source_versions');
    }
};
