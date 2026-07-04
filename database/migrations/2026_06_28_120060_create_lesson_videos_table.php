<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// V0 — video narrato di una LEZIONE, derivato da una presentazione READY (di norma
// la PUBBLICATA, coerente con ciò che vedono gli studenti). Mirror di
// lesson_presentations. Il COPIONE per slide è json `script` = [{slide_number, text}];
// script_status traccia il ciclo del copione (none → draft → confirmed).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_videos', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('lesson_id')->constrained('lessons')->cascadeOnDelete();
            $table->foreignUuid('presentation_id')->nullable()->constrained('lesson_presentations')->nullOnDelete();
            $table->string('file_path')->nullable();            // mp4 in storage privato
            $table->string('status')->default('pending');       // pending|generating|ready|failed
            $table->string('script_status')->default('none');   // none|draft|confirmed
            $table->json('script')->nullable();                 // [{slide_number, text}]
            $table->json('generation_meta')->nullable();
            $table->timestamps();
            $table->index('lesson_id');
            $table->index('presentation_id');
        });

        DB::statement("ALTER TABLE lesson_videos ADD CONSTRAINT lesson_videos_status_check
            CHECK (status IN ('pending', 'generating', 'ready', 'failed'))");
        DB::statement("ALTER TABLE lesson_videos ADD CONSTRAINT lesson_videos_script_status_check
            CHECK (script_status IN ('none', 'draft', 'confirmed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_videos');
    }
};
