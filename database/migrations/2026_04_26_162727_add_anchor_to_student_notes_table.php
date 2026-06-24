<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_notes', function (Blueprint $table) {
            $table->string('anchor', 50)->nullable()->after('module_id');
        });

        // Drop il vecchio UNIQUE (student_id, module_id) che ora è troppo restrittivo
        DB::statement('ALTER TABLE student_notes DROP CONSTRAINT IF EXISTS student_notes_student_id_module_id_unique');

        // Partial unique indexes (Postgres-specific) per gestire NULL anchor:
        // - 1 sola nota generale per (student, module) quando anchor IS NULL
        // - 1 sola nota per (student, module, anchor) quando anchor IS NOT NULL
        DB::statement('CREATE UNIQUE INDEX student_notes_general_uniq ON student_notes (student_id, module_id) WHERE anchor IS NULL');
        DB::statement('CREATE UNIQUE INDEX student_notes_anchored_uniq ON student_notes (student_id, module_id, anchor) WHERE anchor IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS student_notes_general_uniq');
        DB::statement('DROP INDEX IF EXISTS student_notes_anchored_uniq');

        Schema::table('student_notes', function (Blueprint $table) {
            $table->dropColumn('anchor');
            $table->unique(['student_id', 'module_id']);
        });
    }
};
