<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Materia sul chunk RAG: abilita la Minerva a rispondere PER MATERIA e ad allargare
// on-demand ai collegamenti cross-materia. Oggi la materia è solo derivabile via join
// (lesson→topic→subject / artifact→subject / school_classes→subject); qui la
// denormalizziamo su documents_rag. NULL = chunk non classificabile (fallback prudente).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents_rag', function (Blueprint $table) {
            $table->foreignUuid('subject_id')->nullable()->after('teacher_id')
                  ->constrained('subjects')->nullOnDelete();
            $table->index(['scope', 'subject_id']);
            $table->index(['school_class_id', 'subject_id']);
        });

        // Backfill dei chunk esistenti, in ordine di specificità.
        // 1) teacher_shared: la materia è già nel metadata.
        DB::statement("UPDATE documents_rag
            SET subject_id = (metadata->>'subject_id')::uuid
            WHERE subject_id IS NULL AND scope = 'teacher_shared'
              AND (metadata->>'subject_id') IS NOT NULL");

        // 2) via artifact_id → teaching_artifacts.subject_id (teacher_private + class).
        DB::statement("UPDATE documents_rag d
            SET subject_id = a.subject_id
            FROM teaching_artifacts a
            WHERE d.subject_id IS NULL
              AND (d.metadata->>'artifact_id') IS NOT NULL
              AND a.id = (d.metadata->>'artifact_id')::uuid
              AND a.subject_id IS NOT NULL");

        // 3) via lesson_id → lessons.topic → topics.subject_id (chunk di lezione).
        DB::statement("UPDATE documents_rag d
            SET subject_id = t.subject_id
            FROM lessons l
            JOIN topics t ON t.id = l.topic_id
            WHERE d.subject_id IS NULL
              AND (d.metadata->>'lesson_id') IS NOT NULL
              AND l.id = (d.metadata->>'lesson_id')::uuid
              AND t.subject_id IS NOT NULL");

        // 4) fallback classi libere: la classe HA una materia diretta.
        DB::statement("UPDATE documents_rag d
            SET subject_id = c.subject_id
            FROM school_classes c
            WHERE d.subject_id IS NULL AND d.scope = 'class'
              AND d.school_class_id = c.id AND c.subject_id IS NOT NULL");
    }

    public function down(): void
    {
        Schema::table('documents_rag', function (Blueprint $table) {
            $table->dropIndex(['school_class_id', 'subject_id']);
            $table->dropIndex(['scope', 'subject_id']);
            $table->dropConstrainedForeignId('subject_id');
        });
    }
};
