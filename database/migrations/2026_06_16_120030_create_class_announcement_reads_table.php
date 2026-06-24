<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Ricevute di lettura degli annunci di classe. Rispecchia `announcement_reads`.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_announcement_reads', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('class_announcement_id')->constrained('class_announcements')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->timestamp('read_at');
            $table->timestamps();

            $table->unique(['class_announcement_id', 'student_id']);
            $table->index(['student_id', 'class_announcement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_announcement_reads');
    }
};
