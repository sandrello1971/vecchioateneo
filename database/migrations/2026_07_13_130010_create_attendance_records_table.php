<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registro di frequenza — ledger append-only degli eventi di presenza per
 * (studente, corso). Copre sia il SINCRONO (marcatura docente per sessione) sia
 * l'ASINCRONO/FAD (completamento moduli col tempo reale tracciato, accessi, quiz).
 *
 * hours_credited: ore attribuite dall'evento al totale di frequenza. Solo alcuni
 * source le valorizzano (module_completion, instructor_mark); gli eventi di sola
 * traccia (module_access, login, quiz_attempt) restano a 0 per non doppiare le ore.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('course_id')->constrained('courses')->cascadeOnDelete();

            $table->string('type');   // sync_session | async_activity
            $table->string('source'); // module_completion | quiz_attempt | heartbeat | module_access | login | instructor_mark

            $table->uuid('course_session_id')->nullable();
            $table->uuid('module_id')->nullable();

            $table->timestamp('occurred_at');
            $table->decimal('hours_credited', 6, 2)->default(0);

            $table->string('ip', 45)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('course_session_id')->references('id')->on('course_sessions')->nullOnDelete();
            $table->foreign('module_id')->references('id')->on('modules')->nullOnDelete();

            $table->index(['student_id', 'course_id', 'occurred_at']);
            $table->index(['course_id', 'occurred_at']);
            $table->index(['type', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
