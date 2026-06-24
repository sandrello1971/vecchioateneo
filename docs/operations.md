# Officina — Operations runbook

Disciplina operativa per Officina-prod. Aggiornata: 2026-05-22.

## Sync prod

Sempre eseguire dopo modifiche locali sincronizzate dal git checkout:

```bash
/var/www/noscite-atheneum/scripts/sync-prod.sh
```

Include automaticamente i file infrastrutturali ad alto rischio drift
(lezione P0: `Setting.php` rimase indietro mesi causando bug subtili):

- `app/Models/Setting.php`
- `app/Models/Student.php`
- `app/Models/Admin.php`
- `app/Support/helpers.php`
- `app/Providers/AppServiceProvider.php`
- `config/atheneum.php`, `config/app.php`, `config/broadcasting.php`
- `routes/channels.php`
- `bootstrap/app.php`

Verifica checksum md5 dev↔prod post-sync. Esce con error code 1 se rileva drift.

Flag:
- `--dry-run` — mostra cosa farebbe senza eseguire
- `--skip-infrastructure` — salta sync infrastructure (solo cache clear + verify)

## Smoke test post-deploy

Dopo ogni `git push origin main` + sync-prod:

```bash
/var/www/noscite-atheneum/scripts/smoke-test.sh
```

8 check (DB connection, Setting resolve, Setting fallback empty, Admin record,
Conversation model, Mail config, HTTP / 200, HTTP /admin/login 200).
Exit 0 = pass, exit 1 = almeno 1 fail.

## Backup

| Cosa | Frequenza | Retention | Path | Cron |
|---|---|---|---|---|
| **DB PostgreSQL** (`atheneum_db`) | Quotidiano 02:00 | 7 giorni | `/var/backups/atheneum/db/` | postgres |
| **Certificati firmati eIDAS** | Quotidiano 02:30 | 30 giorni | `/var/backups/atheneum/storage/` | root |

Log: `/var/log/atheneum-backup.log`.

### Restore DB
```bash
gunzip < /var/backups/atheneum/db/atheneum_YYYYMMDD_HHMMSS.sql.gz \
    | sudo -u postgres psql -d atheneum_db
```

## Monitoring

| Cosa | Frequenza | Alert |
|---|---|---|
| **Healthcheck daemon** (Reverb + queue worker) | Ogni 5 min | Email a sandrello@noscite.it su down/recovery |
| **Error alert** (laravel.log ERROR/CRITICAL/EMERGENCY) | Ogni ora | Email su nuovi errori dall'ultimo check |
| **Logrotate** (`laravel.log`) | Daily (cron.daily) | Retention 14 giorni, compresso |

Anti-spam healthcheck: state file in `/var/lib/atheneum-healthcheck/` evita
alert ripetuti. Solo 1 alert + 1 recovery email per down-event.

Log: `/var/log/atheneum-healthcheck.log`.

## Cron registrato

```
postgres:
  0 2 * * * /usr/local/bin/atheneum-db-backup.sh

root:
  30 2 * * * /usr/local/bin/atheneum-storage-backup.sh
  */5 * * * * /usr/local/bin/atheneum-healthcheck.sh
  0 * * * * /usr/local/bin/atheneum-error-alert.sh
```

Verifica con: `sudo crontab -l` e `sudo -u postgres crontab -l`.

## Files

| File | Owner | Cosa |
|---|---|---|
| `/usr/local/bin/atheneum-db-backup.sh` | root | DB dump quotidiano |
| `/usr/local/bin/atheneum-storage-backup.sh` | root | Storage rsync quotidiano |
| `/usr/local/bin/atheneum-healthcheck.sh` | root | Healthcheck daemons |
| `/usr/local/bin/atheneum-error-alert.sh` | root | Error log scanner + alert |
| `/etc/logrotate.d/atheneum-laravel` | root | logrotate config |
| `/var/www/noscite-atheneum/scripts/sync-prod.sh` | noscite | Sync dev→prod |
| `/var/www/noscite-atheneum/scripts/smoke-test.sh` | noscite | Smoke test post-deploy |

## Log files

| File | Cosa |
|---|---|
| `/var/log/atheneum-sync.log` | Sync prod operations + drift detection |
| `/var/log/atheneum-backup.log` | Backup DB + storage |
| `/var/log/atheneum-healthcheck.log` | Daemon healthcheck transitions |
| `/var/www/noscite-atheneum/storage/logs/laravel.log` | App log (rotato da logrotate) |

## Procedura di deploy standard

1. Lavora in `/home/noscite/noscite-websites/noscite-atheneum/` (git)
2. `git add ... && git commit -m "..." && git push origin main`
3. `/var/www/noscite-atheneum/scripts/sync-prod.sh`
4. `/var/www/noscite-atheneum/scripts/smoke-test.sh`
5. Se smoke test passa: deploy concluso
6. Se smoke fallisce: indaga prima che gli utenti lo notino, eventualmente `git revert` + ripeti
