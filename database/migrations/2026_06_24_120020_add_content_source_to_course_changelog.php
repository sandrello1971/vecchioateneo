<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// P25.B-a.1 — Il changelog registra entrambe le timeline: applicazioni/rollback del
// formatore (instructor) e del materiale studente (student). Additiva, default 'instructor'.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_changelog', function (Blueprint $table) {
            $table->string('content_source', 12)->default('instructor');
        });

        DB::statement("ALTER TABLE course_changelog ADD CONSTRAINT course_changelog_content_source_check
            CHECK (content_source IN ('instructor', 'student'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE course_changelog DROP CONSTRAINT IF EXISTS course_changelog_content_source_check');
        Schema::table('course_changelog', function (Blueprint $table) {
            $table->dropColumn('content_source');
        });
    }
};
