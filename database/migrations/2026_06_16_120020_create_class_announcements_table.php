<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Annunci del docente a tutta la classe (broadcast, sola lettura per gli
// studenti). Rispecchia `announcements` del mondo corsi, legato alla classe.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_announcements', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('school_class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->foreignUuid('teacher_id')->constrained('students')->cascadeOnDelete();
            $table->string('subject', 200);
            $table->text('body');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['school_class_id', 'created_at']);
            $table->index(['teacher_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_announcements');
    }
};
