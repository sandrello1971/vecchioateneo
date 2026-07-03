<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// V0 — video narrato di un MODULO (corsi Officina), derivato da una presentazione
// READY (di norma la PUBBLICATA). Gemella di create_lesson_videos_table.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_videos', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('module_id')->constrained('modules')->cascadeOnDelete();
            $table->foreignUuid('presentation_id')->nullable()->constrained('module_presentations')->nullOnDelete();
            $table->string('file_path')->nullable();
            $table->string('status')->default('pending');
            $table->string('script_status')->default('none');
            $table->json('script')->nullable();
            $table->json('generation_meta')->nullable();
            $table->timestamps();
            $table->index('module_id');
            $table->index('presentation_id');
        });

        DB::statement("ALTER TABLE module_videos ADD CONSTRAINT module_videos_status_check
            CHECK (status IN ('pending', 'generating', 'ready', 'failed'))");
        DB::statement("ALTER TABLE module_videos ADD CONSTRAINT module_videos_script_status_check
            CHECK (script_status IN ('none', 'draft', 'confirmed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('module_videos');
    }
};
