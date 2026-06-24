<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P29 Fase 2 — documento PDF generato dell'INTERO corso Officina.
 *
 * Gemella di module_documents, ma a livello CORSO: content_hash = hash AGGREGATO
 * dei moduli ordinati (Course::currentContentHash()), così lo stale scatta anche
 * su aggiunta/rimozione/riordino di moduli, non solo su modifica di un content.
 *
 * Renderer = CourseSourcePdfBuilder theme-agnostic (brand GLITCH di piattaforma).
 * Sorgente = SOLO i module.content concatenati (i materials restano fuori).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_documents', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('file_path')->nullable();        // storage/app/private/course-documents/...
            $table->string('status')->default('pending');   // pending|generating|ready|failed
            $table->string('content_hash')->nullable();      // hash AGGREGATO alla generazione (stale-detection)
            $table->json('generation_meta')->nullable();
            $table->timestamps();

            $table->unique('course_id'); // un documento per corso (firstOrCreate)
        });

        DB::statement("ALTER TABLE course_documents ADD CONSTRAINT course_documents_status_check
            CHECK (status IN ('pending','generating','ready','failed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('course_documents');
    }
};
