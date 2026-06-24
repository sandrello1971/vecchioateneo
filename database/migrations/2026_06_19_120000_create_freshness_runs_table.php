<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// P25.2 — Esecuzioni dell'agente di aggiornamento corsi (Course Freshness Agent).
// Ogni run registra l'attività su un corso. In P25.2 l'agente SOLO estrae e verifica:
// `proposals_created` resta 0 (le proposte sono P25.3). Aggancio per course_id interno.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('freshness_runs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('status', 20)->default('running'); // running | completed | failed
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->integer('claims_found')->default(0);
            $table->integer('proposals_created')->default(0); // resta 0 in P25.2
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['course_id', 'started_at']);
        });

        DB::statement("ALTER TABLE freshness_runs ADD CONSTRAINT freshness_runs_status_check
            CHECK (status IN ('running', 'completed', 'failed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('freshness_runs');
    }
};
