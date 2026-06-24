<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quiz "pool + estrazione casuale per tentativo".
 *
 * - quizzes.questions_per_attempt: quante domande estrarre per tentativo dal pool.
 *   NULL = somministra TUTTE le domande (comportamento storico → retrocompat totale).
 * - quiz_attempts.selected_question_ids: gli id delle domande estratte per QUEL
 *   tentativo (stabilità tra refresh/ripresa + base del punteggio). NULL sui
 *   tentativi vecchi e quando si somministrano tutte le domande.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->unsignedInteger('questions_per_attempt')->nullable()->after('randomize_questions');
        });

        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->json('selected_question_ids')->nullable()->after('attempt_number');
        });
    }

    public function down(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropColumn('questions_per_attempt');
        });

        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->dropColumn('selected_question_ids');
        });
    }
};
