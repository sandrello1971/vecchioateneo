<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instructor_note_images', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('instructor_note_id');
            $table->string('file_path');
            $table->bigInteger('file_size');
            $table->string('mime_type', 50);
            $table->timestamps();

            $table->foreign('instructor_note_id')
                ->references('id')->on('instructor_notes')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instructor_note_images');
    }
};
