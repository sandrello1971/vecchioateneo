<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            $table->foreignUuid('student_id')
                ->constrained('students')
                ->cascadeOnDelete();   // GDPR erasure: cancellando lo studente, cancelliamo i suoi certificati

            $table->foreignUuid('course_id')
                ->nullable()
                ->constrained('courses')
                ->nullOnDelete();       // cancellando il corso, conserviamo lo snapshot storico

            $table->foreignUuid('quiz_attempt_id')
                ->nullable()
                ->constrained('quiz_attempts')
                ->nullOnDelete();       // cancellando l'attempt, audit-friendly

            $table->string('code', 32)->unique();
            $table->integer('score');
            $table->timestamp('issued_at');
            $table->string('certification_name');   // snapshot: il nome al momento dell'emissione
            $table->jsonb('metadata')->nullable();  // predisposizione EDC / firme / payload futuri

            $table->timestamps();

            // Idempotenza emissione: una sola riga per (studente, corso) finché entrambi esistono.
            // Con course_id NULL (corso cancellato), PG tratta NULL come distinct e consente
            // più righe per lo stesso studente — comportamento accettabile per snapshot storici.
            $table->unique(['student_id', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
