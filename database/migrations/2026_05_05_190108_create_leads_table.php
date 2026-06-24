<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            $table->string('email')->index();
            $table->string('name');
            $table->string('company');

            // Identificativo della fonte del lead (es. 'mappa-percorso-pdf').
            // Predisposto per gestire più lead magnet diversi in futuro.
            $table->string('source')->index();

            // UTM per attribution
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_content')->nullable();
            $table->string('utm_term')->nullable();

            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 500)->nullable();

            // GDPR
            $table->boolean('privacy_accepted')->default(false);
            $table->timestamp('privacy_accepted_at')->nullable();

            // Stato consegna email
            $table->boolean('email_sent')->default(false);
            $table->timestamp('email_sent_at')->nullable();
            $table->text('email_error')->nullable();

            // Collegamento futuro a Student (UUID, allineato al pattern di certificates).
            // Sarà popolato da Lead::linkToStudent() quando un lead diventa studente.
            $table->foreignUuid('student_id')
                ->nullable()
                ->constrained('students')
                ->nullOnDelete();

            // Estensibilità futura per metadati specifici di altri lead magnet.
            $table->jsonb('metadata')->nullable();

            $table->timestamps();

            // Stesso utente può scaricare più lead magnet diversi: niente unique
            // su email globale, ma indice composto per query "ha già scaricato
            // questo specifico magnet?"
            $table->index(['email', 'source']);

            // Per query analytics tipo "lead di questa source negli ultimi N giorni"
            $table->index(['source', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
