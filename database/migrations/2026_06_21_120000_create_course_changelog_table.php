<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// P25.3c — Storico delle applicazioni (audit). Una riga per proposta applicata (kind=
// 'apply') e una per ogni rollback (kind='rollback'). È AUDIT da conservare: niente
// cascade sul delete del corso (nullOnDelete) — diverso dalle tabelle rigenerabili.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_changelog', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            // Audit conservato anche se il corso/proposta vengono eliminati.
            $table->foreignUuid('course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->foreignUuid('proposal_id')->nullable()->constrained('update_proposals')->nullOnDelete();
            $table->string('version_from', 20);
            $table->string('version_to', 20);
            $table->string('kind', 12)->default('apply'); // apply | rollback
            $table->text('summary');
            $table->foreignUuid('approved_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index('course_id');
        });

        DB::statement("ALTER TABLE course_changelog ADD CONSTRAINT course_changelog_kind_check
            CHECK (kind IN ('apply', 'rollback'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('course_changelog');
    }
};
