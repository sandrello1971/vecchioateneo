# Debito tecnico — noscite-atheneum

Registro dei debiti tecnici noti, da affrontare quando il contesto lo richiede.

## Broadcasting / realtime abbozzato e mai completato

- **Cosa**: il 21/05 è stato abbozzato il supporto realtime via Laravel Echo
  **direttamente in produzione** (`/var/www/noscite-atheneum`), mai committato in
  git. Era presente `resources/js/echo.js` (config `Echo` su broadcaster `reverb`)
  e l'import `import './echo'` in `resources/js/bootstrap.js`.
- **Perché è incompleto e NON è stato recuperato nel repo**:
  - `laravel-echo` e `pusher-js` non sono in `package.json` né in `node_modules`
    (né in dev né in prod): `npm run build` fallirebbe sull'`import './echo'`.
  - Il bundle buildato in `public/build` non contiene Echo: lo scaffolding è
    dormiente, mai entrato in funzione.
  - Lato backend la situazione è solo parziale: il `.env` di prod ha già
    `BROADCAST_CONNECTION=reverb` e le chiavi `REVERB_*`/`VITE_REVERB_*`, ma manca
    `config/reverb.php` nel repo e non risulta un processo Reverb attivo/supervisato.
- **Decisione**: scaffolding lasciato **fuori dal repo**. Quando servirà il
  realtime, va **completato in modo organico**: aggiungere le dipendenze npm,
  pubblicare/configurare `config/reverb.php`, verificare gli env già presenti,
  supervisord per il processo Reverb, definire canali/eventi e ricostruire il bundle.
- **Riferimento**: `echo.js` esiste solo in `/var/www/noscite-atheneum/resources/js/`
  (produzione) come reperto storico.
