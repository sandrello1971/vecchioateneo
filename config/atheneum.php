<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Officina Admin Whitelist — DEPRECATO (P1.2)
    |--------------------------------------------------------------------------
    | La whitelist e' ora gestita via DB nella tabella admins, modificabile
    | dalla UI /admin/admins (AdminAccountController). Questo array vuoto
    | resta come rete di sicurezza: se qualche codice legacy lo consulta
    | ancora, ritorna [] invece di esplodere. Da rimuovere completamente
    | quando saremo certi che nessun altro legge questa chiave.
    */
    'admins' => [],

    /*
    |--------------------------------------------------------------------------
    | Legal Representative Email — SEED DI BACKFILL (storico)
    |--------------------------------------------------------------------------
    | L'abilitazione alla firma dei certificati NON è più legata a questa
    | singola email: è ora un privilegio per-account in DB
    | (admins.can_sign_certificates), gestibile da più amministratori dalla
    | UI /admin/admins (AdminAccountController::signature + middleware
    | EnsureLegalRepresentative).
    |
    | Questo valore resta solo come seed: la migrazione che ha introdotto la
    | colonna lo usa per marcare firmatario il legale rappresentante storico,
    | evitando di lasciare la piattaforma senza nessun firmatario al deploy.
    | A migrazione applicata in tutti gli ambienti, è rimovibile.
    */
    'legal_representative_email' => env('LEGAL_REPRESENTATIVE_EMAIL', 'sandrello@noscite.it'),
];
