<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sessioni SINCRONE di un corso (aula in presenza o webinar live): ognuna ha una
 * data e una durata. La presenza dei discenti a ciascuna sessione viene marcata
 * dal docente/admin e registrata come attendance_record (source instructor_mark).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('course_id')->constrained('courses')->cascadeOnDelete();

            $table->string('title');
            $table->timestamp('scheduled_at');
            $table->integer('duration_minutes');
            $table->string('modality')->default('in_person'); // in_person | live_online
            $table->string('location')->nullable();
            $table->string('created_by')->nullable(); // email admin / id docente
            $table->timestamps();

            $table->index(['course_id', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_sessions');
    }
};
