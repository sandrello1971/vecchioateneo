<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// P26 Fase C — Posizione scelta per l'inserimento di una bozza (HITL: la conferma l'admin).
// + estende course_changelog.kind con 'insert'/'revert' (Fase D). Additiva.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gap_drafts', function (Blueprint $table) {
            // Formatore: il block_id di course_sources DOPO cui inserire (id statico, niente orfananza).
            $table->string('place_formatore_block_id')->nullable();
            // Studente: modulo + ancora testuale verbatim dopo cui fare lo splice HTML.
            $table->foreignUuid('place_student_module_id')->nullable()->constrained('modules')->nullOnDelete();
            $table->text('place_student_anchor')->nullable();
            // La posizione non è mai automatica: vale solo se l'admin la conferma.
            $table->boolean('placement_confirmed')->default(false);
        });

        DB::statement('ALTER TABLE course_changelog DROP CONSTRAINT course_changelog_kind_check');
        DB::statement("ALTER TABLE course_changelog ADD CONSTRAINT course_changelog_kind_check
            CHECK (kind IN ('apply', 'rollback', 'insert', 'revert'))");
    }

    public function down(): void
    {
        Schema::table('gap_drafts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('place_student_module_id');
            $table->dropColumn(['place_formatore_block_id', 'place_student_anchor', 'placement_confirmed']);
        });
        DB::statement('ALTER TABLE course_changelog DROP CONSTRAINT course_changelog_kind_check');
        DB::statement("ALTER TABLE course_changelog ADD CONSTRAINT course_changelog_kind_check
            CHECK (kind IN ('apply', 'rollback'))");
    }
};
