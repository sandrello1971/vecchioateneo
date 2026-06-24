<?php

return [
    // Soglia minima (byte) per considerare integro un backup pg_dump prima di un
    // write distruttivo. Default 1MB: tarato sul DB di PRODUZIONE (~17MB). Su DB
    // piccoli (es. atheneum_test_db) si abbassa via env. Il marcatore di chiusura
    // del dump resta il controllo PRIMARIO contro i troncamenti.
    'backup_min_bytes' => (int) env('REIMPORT_BACKUP_MIN_BYTES', 1_048_576),
];
