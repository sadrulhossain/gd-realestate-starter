# gd-realestate-starter

A small-business website you can run on one VPS: a public portal (Next.js) in two languages, an admin app (React) to edit everything,
and a Laravel API behind both. Content: hero, about us, sections, services, pricing (per service, hidden until switched on), team, testimonials, gallery, FAQ, blog, contact inbox, settings (logo, favicon, currency, social links), users and roles.

| Part | Where | Stack |
|------|-------|-------|
| API | `api/` | Laravel 13, PHP 8.5, PostgreSQL 18, Redis 8, Passport (OAuth 2 + PKCE), spatie/permission |
| Portal | `web/portal` | Next.js 16 (App Router, ISR), Tailwind 4 |
| Admin | `web/admin` | Vite, React 19, TanStack Query, TipTap |
| Shared | `packages/` | design tokens, typed API client, OpenAPI types |

Versions and their security floors: [docs/VERSIONS.md](docs/VERSIONS.md).

## Quick start (development)

You need Docker with Compose and a browser. Nothing else is installed on the host.

```sh
git clone <your fork> site && cd site
cp .env.example .env     # then set APP_KEY=base64:<output of: openssl rand -base64 32> and change every change-me
docker compose up -d --build --wait
docker compose exec php composer install
docker compose exec php php artisan migrate --seed
sh scripts/ensure-test-db.sh   # creates the testing database used by Pest
```

Then open (the `.localhost` names resolve to your machine in modern browsers; no hosts file edit needed):

| URL | What |
|-----|------|
| http://gd-realestate-starter.localhost:28080 | Portal (`/bn` for the second language when enabled) |
| http://admin.gd-realestate-starter.localhost:28080 | Admin (sign in with the superadmin below) |
| http://api.gd-realestate-starter.localhost:28080/docs/api | API reference (Scramble, dev only) |
| http://localhost:28026 | Mailpit, catches the contact-form mail |

First run only: set `SUPERADMIN_EMAIL`, `SUPERADMIN_PASSWORD` and `OAUTH_ADMIN_REDIRECT_URI` in `api/.env` (copy `api/.env.example`), then
`docker compose exec php php artisan migrate --seed`. Change every `change-me` in `.env`.

Optional demo content in English and the second language (not part of `DatabaseSeeder`): `docker compose exec php php artisan db:seed --class="Database\Seeders\Site\SiteDataSeeder"`. Logo and favicon are uploaded in the admin under Settings; the built-in ones show until then. Footer credit: Powered by Orvyx Technologies.

Daily commands:

| Command | What |
|---------|------|
| `docker compose up -d --build --wait` / `docker compose down --remove-orphans` | start (build, wait healthy) / stop |
| `docker compose exec -T php vendor/bin/pest --compact --filter=Name` | targeted Pest tests (omit `--filter` for the full suite) |
| `docker compose exec -T php vendor/bin/pint --test` | code style |
| `docker compose exec -T php vendor/bin/phpstan analyse --memory-limit=1G --no-progress` | Larastan |
| `docker compose exec -T php composer audit` | dependency advisories |
| `docker compose exec -T nginx nginx -t` | nginx config test |
| `docker compose config -q` | compose file validity |
| `docker compose exec -T php php artisan security:routes`, `sh scripts/check-secrets.sh`, `node scripts/check-floors.mjs` | security gates: route/policy audit, secrets scan, version floors |
| `sh scripts/check-ports.sh` | host ports from `.env` are free or ours |
| `docker compose exec -T php php artisan scramble:export --path=storage/openapi.json` | re-export the API spec; move `api/storage/openapi.json` to `packages/api-types/openapi.json`, then `pnpm generate:types` |

Final phase check (stack up): `sh scripts/check.sh`. Targeted tests: `docker compose exec -T php vendor/bin/pest --compact --filter=Name`.

Browser tests (Playwright, official image): `docker compose --profile e2e run --rm --no-deps -T -w /app/web/portal e2e npx playwright test`.

The dev containers do not see file changes on Windows hosts: after editing portal code, stop `node`, delete `web/portal/.next` and start it again.

## Production

One VPS (1 vCPU / 1 GB is the minimum, 2 GB recommended: [docs/SIZING.md](docs/SIZING.md)), your domain, a TLS certificate.
`docker-compose.prod.yml` runs the portal as a Node server, serves the admin as built static files from nginx, publishes only ports 80 and 443,
keeps PostgreSQL and Redis private and restarts everything on failure. Steps: [docs/DEPLOY.md](docs/DEPLOY.md). Backups: `sh scripts/backup.sh`.

## Documentation

| File | Read it for |
|------|-------------|
| [docs/OVERVIEW.md](docs/OVERVIEW.md) | what the product is, what a client gets, the website pages and admin modules |
| [docs/DEPLOY.md](docs/DEPLOY.md) | VPS setup, TLS, first deploy, backups, restore |
| [docs/CUSTOMIZE.md](docs/CUSTOMIZE.md) | brand colours, a second language, new modules and sections |
| [docs/UPGRADE.md](docs/UPGRADE.md) | updating a running site, rollback |
| [docs/PATTERNS.md](docs/PATTERNS.md) | the reference code shapes to copy |
| [docs/ENV.md](docs/ENV.md) | every environment variable |
| [docs/SECURITY.md](docs/SECURITY.md) | review findings, scans, accessibility and performance results |
| [docs/DECISIONS.md](docs/DECISIONS.md), [docs/PHASES.md](docs/PHASES.md) | why things are the way they are, what was built when |
| [CHANGELOG.md](CHANGELOG.md) | releases |

## Repository map

```
api/        Laravel API            docker/     Dockerfiles, nginx templates, prod entrypoints
web/admin   Admin SPA              docs/       the documents above
web/portal  Public portal          scripts/    ports, secrets, version floors, backup, restore
packages/   tokens, api-client, api-types
```
