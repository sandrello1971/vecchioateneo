<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Materiale grezzo del docente. Storage privato:
// storage/app/private/teaching-documents/{teacher_id}/{document_id}/...
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teaching_documents', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('teacher_id')->constrained('students')->cascadeOnDelete();
            $table->string('title');
            $table->string('source_type');                  // audio|youtube|photos|pdf|docx|text
            $table->string('source_url')->nullable();       // per youtube
            $table->json('source_files')->nullable();       // path storage privato, ordinati
            $table->string('status')->default('pending');   // pending|processing|ready|failed
            $table->text('failure_reason')->nullable();
            $table->longText('extracted_text')->nullable(); // trascrizione/OCR in markdown
            $table->json('extraction_meta')->nullable();    // durata/pagine/lingua/metodo/costi
            $table->foreignUuid('subject_id')->nullable()
                  ->constrained('subjects')->nullOnDelete();
            $table->json('tags')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['teacher_id', 'status']);
        });

        DB::statement("ALTER TABLE teaching_documents ADD CONSTRAINT teaching_documents_source_check
            CHECK (source_type IN ('audio', 'youtube', 'photos', 'pdf', 'docx', 'text'))");
        DB::statement("ALTER TABLE teaching_documents ADD CONSTRAINT teaching_documents_status_check
            CHECK (status IN ('pending', 'processing', 'ready', 'failed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('teaching_documents');
    }
};
