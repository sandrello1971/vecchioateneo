<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Pubblicazione di un artefatto su una classe, con permessi.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artifact_publications', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('teaching_artifact_id')->constrained('teaching_artifacts')->cascadeOnDelete();
            $table->foreignUuid('school_class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->boolean('students_can_generate')->default(true);
            $table->boolean('downloadable')->default(false);
            $table->timestamp('published_at')->useCurrent();
            $table->timestamps();
            $table->unique(['teaching_artifact_id', 'school_class_id']);
            $table->index(['school_class_id', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artifact_publications');
    }
};
