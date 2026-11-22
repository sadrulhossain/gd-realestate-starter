#!/bin/sh
# Final check of a phase (needs the stack up: docker compose up -d --build --wait). Quiet: prints the tail of a failing step only.
set -u
cd "$(dirname "$0")/.."
C="docker compose"
log="$(mktemp)"
step() { "$@" >"$log" 2>&1 || { echo "check: FAILED: $*"; tail -n 20 "$log"; rm -f "$log"; exit 1; }; }

step $C config -q
APP_KEY=check step $C -f docker-compose.prod.yml --env-file .env.prod.example config -q
for f in scripts/*.sh docker/*/*.sh; do step sh -n "$f"; done
[ -n "$($C ps --status running -q php)" ] || { echo "check: stack is down, run: docker compose up -d --build --wait"; exit 1; }
step $C exec -T nginx nginx -t
step $C exec -T php vendor/bin/pint --test
step $C exec -T php vendor/bin/phpstan analyse --memory-limit=1G --no-progress
step $C exec -T php vendor/bin/pest --compact
tail -n 3 "$log"
step $C exec -T php composer audit
sh scripts/check-versions.sh
$C exec -T php php artisan security:routes
sh scripts/check-secrets.sh
node scripts/check-floors.mjs | tail -n 1
rm -f "$log"
echo "check: ok"
