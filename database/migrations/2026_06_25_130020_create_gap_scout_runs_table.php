<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// P26 Fase A — Esecuzione (async) dello Scout di copertura su un corso, per l'osservabilità:
// running → completed (gaps_found) | failed (failure_reason, es. credito via AnthropicError).
// Gemello minimale di freshness_runs. Additiva. Non tocca corsi/moduli/studenti.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gap_scout_runs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('status', 12)->default('running'); // running | completed | failed
            $table->integer('gaps_found')->default(0);
            $table->text('failure_reason')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index('course_id');
        });

        DB::statement("ALTER TABLE gap_scout_runs ADD CONSTRAINT gap_scout_runs_status_check
            CHECK (status IN ('running', 'completed', 'failed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('gap_scout_runs');
    }
};
