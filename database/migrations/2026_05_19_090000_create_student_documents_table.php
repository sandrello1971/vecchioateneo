<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_documents', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->uuid('course_id')->nullable();
            $table->uuid('module_id')->nullable();

            $table->string('title');
            $table->string('description')->nullable();
            $table->string('original_filename');
            $table->string('file_path');
            $table->string('file_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('visibility')->default('private');

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('course_id')->references('id')->on('courses')->nullOnDelete();
            $table->foreign('module_id')->references('id')->on('modules')->nullOnDelete();

            $table->index(['student_id', 'visibility']);
            $table->index(['course_id', 'visibility']);
        });

        DB::statement("
            ALTER TABLE student_documents
            ADD CONSTRAINT student_documents_visibility_check
            CHECK (visibility IN ('private', 'instructors'))
        ");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE student_documents DROP CONSTRAINT IF EXISTS student_documents_visibility_check");
        Schema::dropIfExists('student_documents');
    }
};
