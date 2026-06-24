#!/usr/bin/env bash
#
# deploy-atheneum-local.sh — deploy ONE-OFF dal clone locale, SENZA toccare
# GitHub. Identico a deploy-atheneum.sh ma rimuove il blocco `git fetch/
# checkout/pull origin main`: serve quando il clone `main` è GIÀ allineato al
# codice da rilasciare (es. P24 mergiato in locale) e le credenziali GitHub del
# clone non sono configurate (deploy key disabilitate da policy org).
#
# Verifica esplicita: rifiuta di partire se non sei su `main`. Per il resto è
# byte-per-byte la procedura ufficiale (rsync con gli stessi EXCLUDES, composer,
# build, migrate, cache, restart).
#
# Uso (come noscite, che ha sudo con password):
#   ./deploy-atheneum-local.sh --dry-run   # anteprima, non scrive
#   ./deploy-atheneum-local.sh             # deploy
#
set -euo pipefail

SRC="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"   # .../noscite-websites/noscite-atheneum
REPO="$(cd "$SRC/.." && pwd)"                          # clone monorepo
DEST="/var/www/noscite-atheneum"
FPM_SERVICE="php8.4-fpm"
QUEUE_SERVICE="noscite-atheneum-queue.service"

# Vivono SOLO in produzione: mai sovrascritti né cancellati.
# bootstrap/cache ESCLUSO: contiene la config cache (config.php) generata in
# prod; senza l'exclude, --delete la cancella e cambia ownership della dir,
# lasciando l'app senza config (e .env non è leggibile da www-data) → 500.
EXCLUDES=(
  --exclude='.env'
  --exclude='/storage/'
  --exclude='/vendor/'
  --exclude='/node_modules/'
  --exclude='/bootstrap/cache/'
  --exclude='.git/'
)

MODE="APPLY"
DRY=()
if [[ "${1:-}" == "--dry-run" ]]; then
  MODE="DRY-RUN"
  DRY=(--dry-run)
fi

echo "=== deploy-atheneum-local.sh [$MODE] (no GitHub) ==="
echo "REPO: $REPO"
echo "SRC : $SRC"
echo "DEST: $DEST"
echo

# Guardia: deve esistere il repo git e si deve essere su main.
BRANCH_NOW="$(git -C "$REPO" rev-parse --abbrev-ref HEAD 2>/dev/null || echo '?')"
if [[ "$BRANCH_NOW" != "main" ]]; then
  echo "ABORT: il clone non è su 'main' (è su '$BRANCH_NOW'). Esegui: git -C $REPO checkout main"
  exit 1
fi
echo "==> deploy dal commit locale: $(git -C "$REPO" log --oneline -1)"
echo "    (nessun git pull: si rilascia esattamente ciò che è nel clone)"

if [[ "$MODE" == "DRY-RUN" ]]; then
  echo
  echo "==> rsync (dry-run, nessuna scrittura)"
  rsync -a --delete --itemize-changes "${DRY[@]}" "${EXCLUDES[@]}" "$SRC/" "$DEST/"
  echo
  echo "(dry-run: codice non sincronizzato, nessuna migrazione, nessun restart)"
  exit 0
fi

echo "==> manutenzione ON"
php "$DEST/artisan" down || true

echo "==> rsync codice (preserva .env, storage/, vendor/, node_modules/)"
rsync -a --delete --itemize-changes "${EXCLUDES[@]}" "$SRC/" "$DEST/"

echo "==> composer install --no-dev"
composer install --no-dev --optimize-autoloader --working-dir="$DEST"

echo "==> npm ci && build"
( cd "$DEST" && npm ci && npm run build )

echo "==> migrazioni (additive; P24 non ne introduce, ma rilancia eventuali pendenti)"
php "$DEST/artisan" migrate --force

echo "==> seed materie standard (idempotente)"
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

echo "=== deploy completato (locale, senza GitHub) ==="
