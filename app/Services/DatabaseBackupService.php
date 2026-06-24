<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Backup pg_dump del database con verifica d'integrità, per le operazioni
 * distruttive (es. reimport clean-slate). Iniettabile → mockabile nei test.
 *
 * Un dump "buono" è: file esistente, dimensione > 1MB, e con il marcatore di
 * chiusura di pg_dump in coda (se manca, il dump è troncato → NON affidabile).
 */
class DatabaseBackupService
{
    /** Soglia minima di default (1MB, tarata sul DB di prod); override via config/reimport.php. */
    public const MIN_BYTES = 1_048_576;

    /** Marcatore di fine dump (formato plain di pg_dump). */
    private const END_MARKER = 'PostgreSQL database dump complete';

    /**
     * Esegue pg_dump del DB di default in ~/backup_reimport_{label}_{timestamp}.sql.
     *
     * @return string path assoluto del file di backup
     * @throws RuntimeException se pg_dump fallisce
     */
    public function dump(string $label, string $timestamp): string
    {
        $conn = config('database.connections.' . config('database.default'));
        $home = rtrim((string) (getenv('HOME') ?: sys_get_temp_dir()), '/');
        $safeLabel = preg_replace('/[^A-Za-z0-9_-]/', '-', $label);
        $path = "{$home}/backup_reimport_{$safeLabel}_{$timestamp}.sql";

        $process = new Process([
            'pg_dump',
            '-h', (string) $conn['host'],
            '-p', (string) $conn['port'],
            '-U', (string) $conn['username'],
            '-d', (string) $conn['database'],
            '-f', $path,
        ]);
        $process->setEnv(['PGPASSWORD' => (string) ($conn['password'] ?? '')]);
        $process->setTimeout(600);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException('pg_dump fallito: ' . trim($process->getErrorOutput()));
        }

        return $path;
    }

    /** True se il backup esiste, supera la soglia minima e termina col marcatore pg_dump. */
    public function isValid(string $path): bool
    {
        $minBytes = (int) config('reimport.backup_min_bytes', self::MIN_BYTES);
        if (!is_file($path) || filesize($path) < $minBytes) {
            return false;
        }

        // Controllo PRIMARIO anti-troncamento: il dump deve finire col marcatore.
        return str_contains($this->tail($path, 256), self::END_MARKER);
    }

    /** Ultimi $bytes del file (per controllare il marcatore di chiusura). */
    private function tail(string $path, int $bytes): string
    {
        $fh = fopen($path, 'rb');
        if ($fh === false) {
            return '';
        }
        $size = filesize($path);
        fseek($fh, max(0, $size - $bytes));
        $tail = (string) fread($fh, $bytes);
        fclose($fh);

        return $tail;
    }
}
