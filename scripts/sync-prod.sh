#!/bin/bash
#
# sync-prod.sh — sync chirurgico dei file modificati da /home/.../noscite-atheneum/
# verso /var/www/noscite-atheneum/. Include sempre i file infrastrutturali ad
# alto rischio drift (lezione P0: Setting.php drift silente).
#
# Uso:
#   ./sync-prod.sh                       # sync infrastructure + cache clear + checksum verify
#   ./sync-prod.sh --skip-infrastructure # solo cache clear + verify
#   ./sync-prod.sh --dry-run             # mostra cosa farebbe senza eseguire
#
# Logga ogni esecuzione in /var/log/atheneum-sync.log.

set -e

DEV_DIR="/home/noscite/noscite-websites/noscite-atheneum"
PROD_DIR="/var/www/noscite-atheneum"
LOG_FILE="/var/log/atheneum-sync.log"

# File infrastrutturali ad alto rischio drift (lezione P0).
# Da sincronizzare SEMPRE, indipendentemente da quali altri file sono modificati.
INFRASTRUCTURE_FILES=(
    "app/Models/Setting.php"
    "app/Models/Student.php"
    "app/Models/Admin.php"
    "app/Support/helpers.php"
    "app/Providers/AppServiceProvider.php"
    "config/atheneum.php"
    "config/app.php"
    "config/broadcasting.php"
    "routes/channels.php"
    "bootstrap/app.php"
)

DRY_RUN=false
SKIP_INFRA=false

for arg in "$@"; do
    case $arg in
        --dry-run)            DRY_RUN=true ;;
        --skip-infrastructure) SKIP_INFRA=true ;;
    esac
done

log() {
    local msg="[$(date '+%Y-%m-%d %H:%M:%S')] $1"
    echo "$msg"
    echo "$msg" >> "$LOG_FILE" 2>/dev/null || true
}

log "=== Sync prod started (dry_run=$DRY_RUN, skip_infra=$SKIP_INFRA) ==="

if [ "$SKIP_INFRA" = false ]; then
    log "Syncing infrastructure files..."
    for file in "${INFRASTRUCTURE_FILES[@]}"; do
        if [ -f "$DEV_DIR/$file" ]; then
            if [ "$DRY_RUN" = true ]; then
                log "  [DRY] would copy $file"
            else
                cp "$DEV_DIR/$file" "$PROD_DIR/$file"
                log "  copied $file"
            fi
        else
            log "  WARNING: $file not found in dev (skipped)"
        fi
    done
fi

if [ "$DRY_RUN" = false ]; then
    log "Clearing Laravel cache..."
    cd "$PROD_DIR"
    php artisan optimize:clear > /dev/null 2>&1
    log "  cache cleared"
fi

log "Verifying infrastructure files checksum..."
DRIFT_FOUND=false
for file in "${INFRASTRUCTURE_FILES[@]}"; do
    if [ -f "$DEV_DIR/$file" ] && [ -f "$PROD_DIR/$file" ]; then
        DEV_SUM=$(md5sum "$DEV_DIR/$file" | cut -d' ' -f1)
        PROD_SUM=$(md5sum "$PROD_DIR/$file" | cut -d' ' -f1)
        if [ "$DEV_SUM" != "$PROD_SUM" ]; then
            log "  DRIFT: $file (dev=$DEV_SUM prod=$PROD_SUM)"
            DRIFT_FOUND=true
        fi
    fi
done

if [ "$DRIFT_FOUND" = true ]; then
    log "Drift found. Re-run sync without --dry-run or --skip-infrastructure."
    exit 1
fi

log "Sync completed successfully."
