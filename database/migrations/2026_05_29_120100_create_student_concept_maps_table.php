<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_concept_maps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('student_id');
            $table->uuid('course_concept_map_id');
            $table->jsonb('data');
            $table->timestamp('forked_at');
            $table->timestamp('last_edited_at')->nullable();
            $table->timestamps();

            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
            $table->foreign('course_concept_map_id')->references('id')->on('course_concept_maps')->cascadeOnDelete();
            $table->unique(['student_id', 'course_concept_map_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_concept_maps');
    }
};
