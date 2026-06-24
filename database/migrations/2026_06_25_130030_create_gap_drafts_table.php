<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// P26 Fase B — Bozza (formatore + studente) generata per un gap ACCETTATO. Vive in una tabella
// PROPRIA: la Fase B NON tocca mai course_sources/modules/instructor_manual_sections (è la Fase D).
// `status` copre l'intero ciclo: generating (job in corso) → draft (pronta per revisione) →
// approved (pronta per la Fase D) | discarded | failed (errore generazione, in `error`).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gap_drafts', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('coverage_gap_id')->unique()->constrained('coverage_gaps')->cascadeOnDelete();

            $table->longText('formatore_html')->nullable();
            $table->longText('studente_html')->nullable();
            $table->text('note')->nullable();

            $table->string('status', 12)->default('generating'); // generating|draft|approved|discarded|failed
            $table->text('error')->nullable();                    // motivo reale se status=failed

            $table->foreignUuid('reviewed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();
        });

        DB::statement("ALTER TABLE gap_drafts ADD CONSTRAINT gap_drafts_status_check
            CHECK (status IN ('generating', 'draft', 'approved', 'discarded', 'failed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('gap_drafts');
    }
};
