#!/bin/bash
#
# smoke-test.sh — verifica flussi critici Officina post-deploy.
# Esegue 6 check tinker + 2 check HTTP. Exit code 0 = OK, 1 = FAIL.

set +e  # NON exit on first fail (vogliamo girare tutti i check)

cd /var/www/noscite-atheneum
PASS=0
FAIL=0

check() {
    local name="$1"
    local cmd="$2"
    local expected="$3"

    RESULT=$(eval "$cmd" 2>&1)
    if echo "$RESULT" | grep -q "$expected"; then
        echo "  OK $name"
        PASS=$((PASS + 1))
    else
        echo "  FAIL $name"
        echo "       expected: '$expected'"
        echo "       got:      $RESULT"
        FAIL=$((FAIL + 1))
    fi
}

echo "=== Officina Smoke Test === ($(date))"
echo ""

# 1. DB connectivity
check "DB connection" \
    "php artisan tinker --execute=\"echo DB::connection()->getPdo() ? 'OK' : 'FAIL';\"" \
    "OK"

# 2. Settings model resolve (instance_name popolata)
check "Setting::resolve instance_name" \
    "php artisan tinker --execute=\"echo atheneum_setting('instance_name', 'EMPTY');\"" \
    "Officina"

# 3. Setting empty fallback (lezione P0)
check "Setting fallback when empty" \
    "php artisan tinker --execute=\"\App\Models\Setting::put('test_smoke_key', ''); echo atheneum_setting('test_smoke_key', 'FALLBACK'); \App\Models\Setting::where('key', 'test_smoke_key')->delete();\"" \
    "FALLBACK"

# 4. Admin model + record sandrello existing
check "Admin sandrello exists" \
    "php artisan tinker --execute=\"echo \App\Models\Admin::where('email', 'sandrello@noscite.it')->first() ? 'EXISTS' : 'MISSING';\"" \
    "EXISTS"

# 5. Conversation model accessible (messaggistica)
check "Conversation model loaded" \
    "php artisan tinker --execute=\"echo class_exists(\App\Models\Conversation::class) ? 'LOADED' : 'MISSING';\"" \
    "LOADED"

# 6. Mail config valido
check "Mail config valid" \
    "php artisan tinker --execute=\"echo config('mail.default') ? 'OK' : 'MISSING';\"" \
    "OK"

# 7. HTTP: home page risponde 200
check "HTTP / returns 200" \
    "curl -sI https://atheneum.noscite.it/ | head -1" \
    "200"

# 8. HTTP: admin login risponde 200
check "HTTP /admin/login returns 200" \
    "curl -sI https://atheneum.noscite.it/admin/login | head -1" \
    "200"

echo ""
echo "=== Results: $PASS passed, $FAIL failed ==="

if [ "$FAIL" -gt 0 ]; then
    exit 1
fi
exit 0
