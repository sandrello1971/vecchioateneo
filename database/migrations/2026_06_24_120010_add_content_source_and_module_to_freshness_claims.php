<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// P25.B-a.1 — Sorgente del claim: instructor (course_sources) o student (modules.content).
// Per student l'ancora è module_id + claim_text verbatim. Additiva, default 'instructor'.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('freshness_claims', function (Blueprint $table) {
            $table->string('content_source', 12)->default('instructor');
            $table->foreignUuid('module_id')->nullable()->constrained('modules')->nullOnDelete();
        });

        DB::statement("ALTER TABLE freshness_claims ADD CONSTRAINT freshness_claims_content_source_check
            CHECK (content_source IN ('instructor', 'student'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE freshness_claims DROP CONSTRAINT IF EXISTS freshness_claims_content_source_check');
        Schema::table('freshness_claims', function (Blueprint $table) {
            $table->dropConstrainedForeignId('module_id');
            $table->dropColumn('content_source');
        });
    }
};
