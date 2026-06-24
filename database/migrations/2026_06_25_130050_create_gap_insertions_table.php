<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// P26 Fase D — Registro di un INSERIMENTO eseguito, con tutto il necessario per ANNULLARLO
// (reversibilità). Append-only: l'undo ripristina lo stato precedente creando nuove versioni
// (course_sources/student_source_versions) e ripristinando il content_html della sezione live.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gap_insertions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('gap_draft_id')->constrained('gap_drafts')->cascadeOnDelete();
            $table->foreignUuid('course_id')->constrained('courses')->cascadeOnDelete();

            // Formatore (course_sources)
            $table->string('formatore_version_from');           // versione corrente PRIMA dell'inserimento
            $table->string('formatore_version_to');             // nuova versione con i blocchi inseriti
            $table->jsonb('inserted_block_ids');                // id dei blocchi nuovi (meta.origin=gap_insert)

            // Formatore live (instructor_manual_sections): sezione toccata + suo HTML PRE-inserimento
            $table->foreignUuid('instructor_section_id')->nullable()->constrained('instructor_manual_sections')->nullOnDelete();
            $table->longText('instructor_section_html_before')->nullable();

            // Studente (modules.content)
            $table->foreignUuid('student_module_id')->nullable()->constrained('modules')->nullOnDelete();
            $table->string('student_version_from')->nullable();
            $table->string('student_version_to')->nullable();

            $table->string('status', 12)->default('inserted'); // inserted | reverted
            $table->foreignUuid('reviewed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE gap_insertions ADD CONSTRAINT gap_insertions_status_check
            CHECK (status IN ('inserted', 'reverted'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('gap_insertions');
    }
};
