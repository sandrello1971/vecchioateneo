<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P28 — presentazione .pptx generata per un MODULO di corso Officina.
 *
 * Tabella PARALLELA e additiva, gemella di lesson_presentations: NON tocca la
 * tabella/flusso Schola. Il generatore (build_pptx.py + orchestratore condiviso)
 * è lo stesso; cambia solo la sorgente (module.content) e il brand (sempre
 * piattaforma/GLITCH, i corsi Officina non hanno scuola).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_presentations', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('module_id')->constrained('modules')->cascadeOnDelete();
            $table->string('file_path')->nullable();        // storage/app/private/module-presentations/...
            $table->string('status')->default('pending');   // pending|generating|ready|failed
            $table->json('generation_meta')->nullable();
            $table->timestamps();

            $table->unique('module_id'); // una presentazione per modulo (firstOrCreate)
        });

        DB::statement("ALTER TABLE module_presentations ADD CONSTRAINT module_presentations_status_check
            CHECK (status IN ('pending','generating','ready','failed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('module_presentations');
    }
};
