<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Guardia per l'uso di pgvector. Centralizza il check "posso usare il tipo
 * vector?" così migrazione, RagService e comandi condividono la stessa
 * semantica e la produzione (senza estensione) degrada in sicurezza.
 *
 * Storia: la prima migrazione documents_rag abortiva la transazione tentando
 * ALTER ... vector(1536) senza l'estensione. Da allora il pezzo pgvector è
 * SEMPRE condizionato a questo check sul catalogo (estensione realmente
 * CREATA nel database corrente), mai a un try/catch dentro transazione.
 */
class PgVector
{
    /**
     * True solo se la connessione è PostgreSQL E l'estensione `vector` è
     * effettivamente creata nel database (pg_extension): è questa la
     * condizione che garantisce l'esistenza del tipo `vector`.
     *
     * Nota: si controlla pg_extension (estensione CREATA), non
     * pg_available_extensions (solo disponibile a disco): in prod il binario
     * potrebbe esserci senza CREATE EXTENSION, e l'ALTER fallirebbe comunque.
     */
    public static function available(?string $connection = null): bool
    {
        try {
            $conn = DB::connection($connection);
            if ($conn->getDriverName() !== 'pgsql') {
                return false;
            }

            return $conn->selectOne("SELECT 1 AS ok FROM pg_extension WHERE extname = 'vector'") !== null;
        } catch (Throwable) {
            // Connessione assente o catalogo non interrogabile → degrada a "non disponibile".
            return false;
        }
    }

    /**
     * Formatta un array di float nel literal testuale accettato da pgvector:
     * "[0.1,0.2,...]". Da usare con un cast esplicito ::vector nel SQL.
     */
    public static function toLiteral(array $vector): string
    {
        return '[' . implode(',', array_map(static fn ($v) => (float) $v, $vector)) . ']';
    }
}
