#!/usr/bin/env bash
#
# deploy-atheneum.sh — PROCEDURA UFFICIALE di deploy di noscite-atheneum in
# produzione, dalla PROSSIMA volta in poi. (Il primo salto pkg1-6 → fase 2 è
# stato fatto a mano con swap-as-copy: build completa in -new → swap → -old.)
# Gemello di deploy-videoai.sh.
#
# L'app è una SOTTOCARTELLA del monorepo, quindi prod NON è un clone git: si
# sincronizza via rsync dal clone monorepo (/home) verso /var/www, PRESERVANDO
# ciò che vive solo in produzione (.env, storage/, vendor/, node_modules/).
#
# Uso:
#   ./deploy-atheneum.sh --dry-run   # mostra cosa cambierebbe, non scrive nulla
#   ./deploy-atheneum.sh             # esegue il deploy completo
#
set -euo pipefail

SRC="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"   # .../noscite-websites/noscite-atheneum
REPO="$(cd "$SRC/.." && pwd)"                          # clone monorepo
DEST="/var/www/noscite-atheneum"
BRANCH="main"
FPM_SERVICE="php8.4-fpm"
QUEUE_SERVICE="noscite-atheneum-queue.service"

# Vivono SOLO in produzione: mai sovrascritti né cancellati da --delete
# (le regole di exclude proteggono anche dalla cancellazione). HARDCODED.
EXCLUDES=(
  --exclude='.env'             # segreti di produzione
  --exclude='/storage/'        # file caricati (certificati, materiali), logs
  --exclude='/vendor/'         # dipendenze PHP installate in prod
  --exclude='/node_modules/'   # dipendenze node installate in prod
  --exclude='/bootstrap/cache/' # config/route/view cache di prod: senza questo
                               # --delete la cancella → app senza config → 500
                               # (.env non è leggibile da www-data)
  --exclude='.git/'
)

MODE="APPLY"
DRY=()
if [[ "${1:-}" == "--dry-run" ]]; then
  MODE="DRY-RUN"
  DRY=(--dry-run)
fi

echo "=== deploy-atheneum.sh [$MODE] ==="
echo "REPO: $REPO"
echo "SRC : $SRC"
echo "DEST: $DEST"
echo

echo "==> git pull ($BRANCH)"
git -C "$REPO" fetch origin
git -C "$REPO" checkout "$BRANCH"
git -C "$REPO" pull --ff-only origin "$BRANCH"

if [[ "$MODE" == "DRY-RUN" ]]; then
  echo
  echo "==> rsync (dry-run, nessuna scrittura)"
  rsync -a --delete --itemize-changes "${DRY[@]}" "${EXCLUDES[@]}" "$SRC/" "$DEST/"
  echo
  echo "(dry-run: codice non sincronizzato, nessuna migrazione, nessun restart)"
  exit 0
fi

# artisan gira come utente del deploy (noscite), NON come www-data: www-data non
# può leggere .env (640 noscite) né scrivere bootstrap/cache → config:cache
# bakerebbe i default (DB_CONNECTION→sqlite) e l'app andrebbe in 500. noscite
# legge .env e possiede l'albero; i file generati restano leggibili da www-data.
echo "==> manutenzione ON"
php "$DEST/artisan" down || true

echo "==> rsync codice (preserva .env, storage/, vendor/, node_modules/)"
rsync -a --delete --itemize-changes "${EXCLUDES[@]}" "$SRC/" "$DEST/"

echo "==> composer install --no-dev"
composer install --no-dev --optimize-autoloader --working-dir="$DEST"

echo "==> npm ci && build"
( cd "$DEST" && npm ci && npm run build )

echo "==> migrazioni (additive; se una fallisce, lo script si ferma)"
php "$DEST/artisan" migrate --force

echo "==> seed materie standard (idempotente: firstOrCreate, non tocca le custom)"
php "$DEST/artisan" db:seed --class=SubjectSeeder --force

echo "==> cache config/route/view"
php "$DEST/artisan" config:cache
php "$DEST/artisan" route:cache
php "$DEST/artisan" view:cache

echo "==> reload php-fpm + restart queue worker"
sudo systemctl reload "$FPM_SERVICE"
sudo systemctl restart "$QUEUE_SERVICE"

echo "==> manutenzione OFF"
php "$DEST/artisan" up

echo "=== deploy completato ==="
