<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_concept_maps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('course_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->jsonb('data');
            $table->string('visibility', 16)->default('draft');
            $table->boolean('ai_generated')->default(false);
            $table->timestamp('ai_generated_at')->nullable();
            $table->string('content_hash')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            $table->index(['course_id', 'visibility', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_concept_maps');
    }
};
