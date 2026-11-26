#!/bin/sh
# Release check: EXPLAIN and timing of the public property search on 10,000 rows (budget: p95 under 200 ms).
# Run it on a THROWAWAY production-like stack: it fills an empty database with generated rows and does not remove them.
#   COMPOSE="docker compose -p bench -f docker-compose.prod.yml --env-file .env.bench" sh scripts/bench-search.sh --seed=10000
# The stack must be up and seeded (`php artisan db:seed --force`). Add --plans for the full query plans.
set -eu
cd "$(dirname "$0")/.."

: "${COMPOSE:?set COMPOSE to a throwaway stack (see the top of this script)}"
$COMPOSE exec -T php php artisan bench:search "$@"
