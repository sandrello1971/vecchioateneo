<?php

use App\Support\PgVector;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Colonna embedding vector(768) + indice HNSW (cosine) su documents_rag, per il
 * RAG vettoriale Schola (pre-pacchetto 6). 768 = dimensioni del modello
 * paraphrase-multilingual-mpnet-base-v2 esposto da videoai /api/embeddings.
 *
 * SICUREZZA SENZA PGVECTOR (vedi bug storico in 03_create_documents_rag_table):
 *  - migrazione NON transazionale: un eventuale problema sul passo vettoriale
 *    non può travolgere altro;
 *  - il blocco DDL gira SOLO se l'estensione `vector` è realmente creata nel DB
 *    (PgVector::available, check su pg_extension). In prod l'estensione non c'è
 *    ancora → la migrazione fa uno skip esplicito e non esplode.
 *
 * RICONCILIA il legacy: la create-migration, quando pgvector è presente, aveva
 * aggiunto `embedding vector(1536)` + indice ivfflat (dimensione OpenAI, non la
 * nostra). Qui si elimina quella colonna/indice (mai popolata: il retrieval era
 * ILIKE) e si ricrea a 768 con HNSW. Idempotente.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    private const DIM = 768;
    private const INDEX = 'documents_rag_embedding_idx';

    public function up(): void
    {
        if (!PgVector::available()) {
            // Prod (estensione non ancora creata) o motore non-pgsql: skip sicuro.
            // La colonna arriverà quando, al deploy, verrà eseguita la sessione
            // pgvector (CREATE EXTENSION) e poi questa migrazione.
            return;
        }

        // Rimuove un'eventuale colonna legacy (es. vector(1536)+ivfflat della
        // create-migration) per ricrearla con la dimensione e l'indice corretti.
        DB::statement('DROP INDEX IF EXISTS ' . self::INDEX);
        DB::statement('ALTER TABLE documents_rag DROP COLUMN IF EXISTS embedding');

        DB::statement('ALTER TABLE documents_rag ADD COLUMN embedding vector(' . self::DIM . ')');

        // HNSW per cosine: buon recall/latency, nessun parametro lists da tarare
        // come ivfflat. Si costruisce su tabella (qui) ancora senza embedding.
        DB::statement(
            'CREATE INDEX ' . self::INDEX . ' ON documents_rag '
            . 'USING hnsw (embedding vector_cosine_ops)'
        );
    }

    public function down(): void
    {
        if (!PgVector::available()) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS ' . self::INDEX);
        DB::statement('ALTER TABLE documents_rag DROP COLUMN IF EXISTS embedding');
    }
};
