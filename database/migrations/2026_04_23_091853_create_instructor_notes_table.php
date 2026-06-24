<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instructor_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('instructor_id');
            $table->uuid('course_id');
            $table->uuid('module_id')->nullable();
            $table->uuid('instructor_manual_section_id')->nullable();

            $table->string('kind', 30);
            $table->string('title', 200);
            $table->text('body_markdown');
            $table->jsonb('tags')->nullable();
            $table->boolean('is_shared')->default(false)->index();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('instructor_id')->references('id')->on('students')->cascadeOnDelete();
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            $table->foreign('module_id')->references('id')->on('modules')->nullOnDelete();
            $table->foreign('instructor_manual_section_id')->references('id')->on('instructor_manual_sections')->nullOnDelete();

            $table->index(['course_id', 'module_id']);
            $table->index(['instructor_id', 'course_id']);
            $table->index('kind');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instructor_notes');
    }
};
