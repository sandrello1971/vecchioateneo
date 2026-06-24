<?php

namespace App\Services\Schola;

// Costruzione sicura di un CSV in memoria (escaping/quoting via fputcsv) per
// riusare la pipeline di import sui form di inserimento singolo.
class ImportCsv
{
    /** Intestazione + una riga dati, come stringa CSV (delimitatore virgola, quoting automatico). */
    public static function oneRow(array $header, array $row): string
    {
        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, $header);
        fputcsv($fh, $row);
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);

        return $csv;
    }
}
