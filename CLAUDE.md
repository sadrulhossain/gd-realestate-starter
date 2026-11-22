# gd-realestate-starter

## Repo map
- `api/` — Laravel API (PHP 8.5, PostgreSQL 18.6, Redis 8.10.3 for cache, sessions, queue)
- `web/admin` — Next.js admin app
- `web/portal` — Next.js public portal
- `packages/` — shared packages
- `docker/` — Dockerfiles and compose config
- `docs/` — PHASES.md, VERSIONS.md, ENV.md, DECISIONS.md, PATTERNS.md

## Commands (docker compose and sh scripts only; never make)
- `docker compose up -d --build --wait` / `docker compose down --remove-orphans` — start / stop the stack
- `sh scripts/check-ports.sh` — check host ports from .env are free or ours
- `docker compose exec -T php vendor/bin/pest --compact --filter=...` — targeted Pest tests
- Final check (once, at the end of a phase; needs the stack up): `sh scripts/check.sh`

## Rules
1) One phase at a time; read only CLAUDE.md, the phase row and files it names.
2) No repo-wide exploring, no unrelated refactors, no dependency not in VERSIONS.md.
3) Reuse the reference patterns in docs/PATTERNS.md; do not invent new ones.
4) While working run only targeted tests (pest --filter=...); run the final check once at the end.
5) Keep command output short (quiet flags, | tail -n 20). Never paste logs back.
6) No screenshots unless the phase says so.
7) Finish: tick the phase, git commit "phase N: title", reply in 8 lines or fewer
   (done, deviations, decisions I must make), then STOP and wait for approval.
8) Commits and PRs: never add a Claude signature, Co-Authored-By trailer, "Generated with" line
   or any other Claude attribution to commit messages, PR descriptions, code or docs. Anywhere.
9) Language: English only (portal and admin). Keep LOCALE_SUPPORTED=en; do not build or seed second-language content unless told to.
