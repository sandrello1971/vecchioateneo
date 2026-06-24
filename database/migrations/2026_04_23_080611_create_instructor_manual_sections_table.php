<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instructor_manual_sections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('material_id');
            $table->uuid('course_id');
            $table->uuid('module_id')->nullable();

            $table->string('title');
            $table->string('anchor', 100);
            $table->integer('heading_level');
            $table->integer('sort_order');
            $table->longText('content_html');
            $table->boolean('module_assigned_manually')->default(false);

            $table->timestamps();

            $table->foreign('material_id')->references('id')->on('materials')->cascadeOnDelete();
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            $table->foreign('module_id')->references('id')->on('modules')->nullOnDelete();

            $table->unique(['material_id', 'anchor']);
            $table->index(['course_id', 'module_id']);
        });

        Schema::table('materials', function (Blueprint $table) {
            $table->timestamp('sections_extracted_at')->nullable()->after('content_html');
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn('sections_extracted_at');
        });
        Schema::dropIfExists('instructor_manual_sections');
    }
};
