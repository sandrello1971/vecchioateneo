<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Migrazione NON transazionale di proposito.
     *
     * La versione precedente creava la tabella e poi tentava
     * `ALTER TABLE … ADD COLUMN embedding vector(1536)` dentro un try/catch.
     * Bug latente: senza pgvector quell'ALTER fallisce e — essendo la
     * migrazione avvolta in una transazione — ABORTA l'intera transazione,
     * quindi al commit Postgres fa rollback e `documents_rag` non viene mai
     * creata (il catch PHP non salva la transazione Postgres ormai aborted).
     *
     * Fix: la create deve avvenire SEMPRE; il pezzo pgvector è opzionale ed
     * eseguito solo se l'estensione `vector` è effettivamente presente nel
     * database (check esplicito sul catalogo, non affidandosi al catch).
     * Disabilitando la transazione di migrazione, un eventuale problema sul
     * passo embedding non può comunque travolgere la create.
     *
     * NB stato 06/06: in dev/test/prod pgvector NON è installato e il RAG
     * gira keyword/ILIKE (RagService::search*). Il passaggio a RAG vettoriale
     * (installazione pgvector, CREATE EXTENSION, colonna embedding, backfill,
     * riscrittura retrieval) è prerequisito del pacchetto 6 di Schola — vedi
     * docs/schola/SPEC.md, "Stato reale RAG".
     */
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::create('documents_rag', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('course_id')->nullable()->constrained('courses')->cascadeOnDelete();
            $table->foreignUuid('module_id')->nullable()->constrained('modules')->cascadeOnDelete();
            $table->string('title');
            $table->longText('content');
            $table->string('file_path')->nullable();
            $table->integer('chunk_index')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        // Colonna vettoriale: creata solo se l'estensione pgvector è già
        // attiva in questo database. Niente CREATE EXTENSION qui (richiede
        // privilegi e fa parte della sessione dedicata pre-pacchetto 6).
        if ($this->pgvectorEnabled()) {
            DB::statement('ALTER TABLE documents_rag ADD COLUMN embedding vector(1536)');
            DB::statement('CREATE INDEX documents_rag_embedding_idx ON documents_rag USING ivfflat (embedding vector_cosine_ops) WITH (lists = 10)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('documents_rag');
    }

    /**
     * True solo se siamo su PostgreSQL e l'estensione `vector` risulta
     * effettivamente creata nel database corrente (pg_extension).
     */
    private function pgvectorEnabled(): bool
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return false;
        }

        return DB::selectOne("SELECT 1 FROM pg_extension WHERE extname = 'vector'") !== null;
    }
};
