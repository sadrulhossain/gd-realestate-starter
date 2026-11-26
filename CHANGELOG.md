# Changelog

Format: [Keep a Changelog](https://keepachangelog.com), versions follow [SemVer](https://semver.org).
Each entry lists new environment variables and seeders to run, because upgrades need them (see docs/UPGRADE.md).

## [1.0.0] - 2026-11-26

First release of the real estate kit, forked from the business-site template (its 1.0.0 plus the phase 17 changes, commit `3eade4d`).

### Added
- **Listings:** locations (country, city, area), property types, amenities, properties with media and a status workflow with history, developers, projects, units, publish a unit as a property.
- **Search:** public filters, sorting, pagination, full-text with a typo-tolerant fallback, radius search, saved and compared lists.
- **Sales:** agents with an own-records scope, public enquiries (captcha, honeypot, throttle), lead pipeline and notes, viewings with conflict detection, queued emails, reports with CSV export.
- **Admin:** screens for all of the above; dashboard cards.
- **Portal:** real estate design system, property search and detail, agents, location pages, projects, enquiry and viewing forms, sitemap and JSON-LD additions.
- **Release checks:** `scripts/check.sh`, `scripts/check-versions.sh`, `scripts/bench-search.sh` (`php artisan bench:search`).

### Changed
- PostgreSQL 18 (`pg_trgm`, `cube`, `earthdistance`) replaces MariaDB; Redis 8 holds cache, sessions, queue and throttle state. Backups use `pg_dump`.
- English only. The Makefile is removed.

### Security
- Pins and floors raised to the November 2026 releases: PHP 8.5.13, nginx 1.30.8, PostgreSQL 18.7, Symfony 8.1.12. The PHP images upgrade `libxml2` (CVE-2026-86144).

### Notes
- This is a new product, not an upgrade path from a template site: there is no data migration from MariaDB.
- Environment variables: docs/ENV.md and `.env.prod.example`. New since the template: `POSTGRES_*`, `REDIS_*`, `PHPREDIS_VERSION`. Seeders: run `php artisan db:seed --force` once on first deploy.
- Known limits: the portal CSP still allows inline scripts; scan results and accepted findings are in docs/SECURITY.md.

[1.0.0]: https://github.com/sadrulhossain/gd-realestate-starter/releases/tag/v1.0.0
