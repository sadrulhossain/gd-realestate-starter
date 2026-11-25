# Reference patterns

Copy these shapes, do not invent new ones. Reference module: Sections (`Section*` under `api/app`);
singleton reference: Settings. Paths below are relative to `api/app` unless noted.

## New module checklist
1. **Migration + Model**: `#[Fillable]`, `casts()`. Bilingual text = columns `x` + `x_2nd`, `use HasSecondLanguage`.
2. **Policy** `Policies/<Model>Policy.php`: one method per action, `$user->checkPermissionTo('<module>.<action>')`.
   Permission names are `<module>.<action>`; the superadmin passes via `Gate::before`.
3. **FormRequest** `Http/Requests/Api/V1/<Action><Model>Request.php`: `authorize()` returns
   `$this->user()?->can('<ability>', $model)` (403 comes before validation). `rules()` use `sometimes`
   for PATCH and wrap bilingual fields in `Bilingual::rules([...])`.
4. **Resource** `Http/Resources/Api/V1/<Model>Resource.php`: explicit field list, both languages, no model dumps.
5. **Controller** `Http/Controllers/Api/V1/<Model>Controller.php`: thin. Reads call `Gate::authorize()`; writes
   type-hint the FormRequest, `$model->update($request->validated())`, return the Resource. The first docblock
   line is the Scramble title. Never build error JSON by hand: `Exceptions/JsonErrorRenderer` shapes it.
6. **Routes** in `routes/api.php`, inside the `auth:api` group: `apiResource(...)->only([...])`, `apiSingleton`
   for one-row tables; no create/delete for seeded rows. Non-model routes use `->middleware('can:<perm>')`.
7. **Seeder** `database/seeders/<Module>ModuleSeeder.php` extends `ModuleSeeder` (permissions, menu tree, role
   grants). Seed defaults with `firstOrCreate` so re-seeding never overwrites edits. Add it to `DatabaseSeeder`.
8. **Tests** `tests/Feature/<Module>/`: 401 anonymous, 403 missing permission, happy path, 422 validation
   (assert the field keys), seeder idempotence. Authenticate with `actingAsRole($role, [$perms])` (`tests/Pest.php`).

## Uploads
- `Services/MediaService`: `store($file, '<dir>')` returns the path kept in the model's `image` column;
  `describe($path)` goes in the Resource; `delete($old)` on replace or remove.
- Images upload through their own multipart endpoint: `POST|DELETE <resource>/{id}/image`
  (`SectionImageController`). Validate with `MediaService::imageRules()` (mime by content, size, dimensions).
- Files live at `<dir>/<random>/original.<ext>`; the queued `Jobs/GenerateWebpVariants` adds `<width>.webp`.

## Config and environment
- `env()` is read only in `config/*.php` (e.g. `config/locales.php`, `config/uploads.php`).
- Tests force their environment in `tests/bootstrap.php` (testing DB, array cache, sync queue); compose env
  beats phpunit.xml. Each compose service lists its own `environment:` (see `x-php` in `docker-compose.yml`).

## Public reads, contact, webhook (phase 6)
- Public reads live in the `LocalizePublicResponse` route group (`public/*`): the controller's `publicIndex`/`publicShow` skips
  `Gate::authorize` and returns visible rows only. `?lang=` collapses `x`/`x_2nd` pairs; no per-resource code is needed.
- Rich text: assign it to a model attribute whose setter calls `Services/HtmlSanitizer` (see `Post::body`); never sanitise in controllers.
- Public forms: honeypot field (`prohibited` rule), `Services/Captcha` checked in the FormRequest's `after()`, named limiter in
  `AppServiceProvider`, queued Notification to `Notification::route('mail', ...)`. Answer with `{"data":{"received":true}}`.
- Content-change webhook: add the model to the list in `AppServiceProvider::boot()` (it gets `ContentObserver`); tag = table name.
  Not for private data (contact messages). Tests use `Http::fake()` per test.
- OpenAPI: `php artisan scramble:export --path=storage/openapi.json` (php container) exports Scramble's spec to `packages/api-types/openapi.json`; commit it when routes change.

## Frontend (phase 7)
- Theme: import `tailwindcss` then `@gd/tokens/<app>.css` in the app's one CSS file. Use token utilities (`bg-primary-600`, `text-ink`,
  `border-line`, `font-display`), never raw hex. New tokens go in packages/tokens, not in an app.
- Data: type API data with `components['schemas'][...]` from `@gd/api-types`; call the API with `@gd/api-client` (`createClient`), never raw fetch.
- Regenerate after API changes: `scramble:export` (migrated DB), then `pnpm generate:types`; commit both outputs.
- Tailwind only sees literal class names: map values to full class strings, do not build them with template pieces.

## Admin module (phase 8)
- A CRUD screen is one entry in `web/admin/src/modules.ts`: `key` (permission prefix: `<key>.view|create|update|delete`), `path` (matches the
  `/me/menus` row), `endpoint`, `columns`, `fields` (`bilingual: true` renders "Title (EN)" / "Title (FR)" from `/v1/meta/locales`). The router,
  permission gate and buttons follow from it; add a page component only when the screen is not plain CRUD.
- Calls go through `src/api.ts` (token + refresh handled); never read tokens from the store in components. Use `can('x.y')` for UI gating,
  the API remains the authority.
- Shared pieces: `DataTable`, `FormField`, `BilingualField`, `ConfirmDialog`, `Toast`; do not add a second of any.

## Admin modules (phase 9)
- CrudPage extensions, each used by two modules: `type: 'checkgroup'` (string[]; grouped options render a matrix) with `optionsFrom`
  (options fetched from the API) for users/roles; `reorder: {endpoint, group?}` (drag rows, sends `PUT {ids}`) for sections/menus.
- Inline boolean save in a table cell: `ToggleCell` from a column's `render`. Not a CrudPage feature.
- Single-record screens are not CrudPage: `SettingsPage` + a `SingletonConfig` (tabs of dot-path fields, e.g. `hours.mon.open`).
- API: reorder endpoints are `PUT <resource>/reorder {ids}` registered before the resource route (`ReorderRequest` subclass per table).
  The superadmin role is never assignable, editable or deletable through the API; a non-superadmin can only grant permissions they hold.

## Content modules (phase 10)
- `image: true` on a CrudConfig adds the image panel to the edit form (`POST|DELETE <endpoint>/<id>/image`); show it in the list with a column `render`.
- Uploads use `lib/upload.ts` `uploadFile` (XHR, for progress; same token refresh as the client). Validate with `checkImage` first, the API stays the authority.
- Bulk upload is `GalleryUploader` (create item, then upload; a failed upload deletes the empty item), mounted via `pages` in router.tsx.
- Read-only screens that are not CrudPage (contact inbox) are a page plus an `InboxConfig` (`messagesConfig.ts`).

## Blog and profile (phase 11)
- Rich text: field `type: 'richtext'` (TipTap, `components/RichText.tsx`, HTML in and out; works with `bilingual`). Keep its extensions inside the
  HtmlSanitizer allowlist (api `HtmlSanitizer::ALLOWED`); add a tag there first if the editor needs one.
- `status` (draft|published) is the API's view of `is_published` (`ResolvesPostStatus`); forms use `status`, never the boolean.
- Own account: `PATCH /v1/me` (`ProfileController`), page `pages/Profile.tsx`; no permission, any signed-in user.

## Portal (phase 12)
- Locales: `lib/i18n.ts` reads LOCALE_DEFAULT / LOCALE_SUPPORTED. `proxy.ts` rewrites unprefixed paths to `/<default>/...` and redirects an explicit
  default prefix away; pages live under `app/[locale]/`. Build links with `localePath(locale, path)`, never by hand. Env is server-only: client
  components take `locales` / `unprefixed` as props (see `LanguageSwitcher`).
- Text: `messages/en.json` (default locale) and `messages/second.json` (second); add every key to both. Content text comes from the API with `?lang=`.
- Data: only through `lib/api.ts` (`apiFor(locale)`, server side, `API_INTERNAL_URL`). Reads are `revalidate: 3600` and tagged with the API table name;
  `POST /api/revalidate` (HMAC, same secret as the API webhook) expires a tag. New public resource: add it to `TAGS` there.
- Sections: add a view to `components/sections/index.tsx` keyed by the section `key`; the renderer skips `is_visible: false` and falls back to a generic view.
- Styling: logical utilities only (`ms-`, `me-`, `ps-`, `pe-`, `text-start`, `border-s`), so RTL locales work. `images.unoptimized` is on: use `components/Media.tsx`.
- Dev note: after changing a server component that a client component consumes, a stale Turbopack cache can serve old props; `rm -rf web/portal/.next` and restart node.
- Screenshots: `docker compose --profile e2e run --rm --no-deps -T -w /app/web/portal e2e npx playwright test`.

## Portal pages (phase 13)
- A page for a section is `lib/section-page.tsx` `sectionPage('<key>', [extra keys])` in a two-line `page.tsx`: it renders the same `SectionBlock` view as Home
  (title becomes the h1) and 404s when the section is hidden. Add the key to `SECTION_PAGES` so the header links to the page instead of a Home anchor.
- Static legal text (`privacy`, `terms`) lives in `messages/*.json` under `legal`, with `{site}` / `{email}` filled from settings (`lib/legal-page.tsx`).
  It is a generic template: the business must review it.
- `getMessages` falls back key by key to `en.json`, so a missing or empty entry in `second.json` shows the default text. API content does the same via `?lang=`.
- 404: `app/[locale]/[...rest]/page.tsx` calls `notFound()` for unknown paths; `not-found.tsx` must not read request headers (that makes every locale route dynamic, no ISR): `NotFoundContent` picks the locale from the URL.
- Proxy matcher: keep the double backslash in `.*\..*`; a single one silently matches nothing useful. After editing `proxy.ts` restart node with a clean `.next`.

## Blog, contact and SEO (phase 14)
- Every page builds its metadata with `lib/seo.ts` `pageMetadata({locale, path, siteName, ...})`: title, description, canonical, hreflang (all locales + x-default), Open Graph, Twitter.
  `SITE_URL` is the public origin used for all absolute URLs; `metadataBase` is set in the locale layout.
- JSON-LD goes through `components/JsonLd.tsx` (escapes `<`): LocalBusiness on Home, Article on a post. Build the objects in `lib/seo.ts`.
- `app/sitemap.ts` and `app/robots.ts` use the same cached, tagged fetches as the pages, so the revalidate webhook refreshes them. New public page: add it to the sitemap.
- Blog: `/blog` (`?category=<slug>`, `?page=n`, canonical is the plain list), `/blog/<slug>`. Post `body` is sanitised by the API and rendered as HTML in `.post-body`.
- Contact: `components/ContactForm.tsx` talks only to `app/api/contact/*` (server proxies to the API; the captcha is one-time, refetched after every submit). The honeypot field is `website`.
- Smoke tests: `web/portal/e2e/smoke.spec.ts` (`docker compose --profile e2e run --rm --no-deps -T -w /app/web/portal e2e npx playwright test smoke`). The contact test leaves one message with subject `e2e-smoke`; the blog test skips without published posts.

## Security and quality gates (phase 15)
- Security gates: `php artisan security:routes` (every `auth:api` route needs `can:` or a policy/Gate call; own-account routes are listed in `AuditRoutes::ALLOWED`), `scripts/check-secrets.sh`, `scripts/check-floors.mjs`. A new route gets its check; do not add allowlist entries.
- nginx: `include /etc/nginx/snippets/security-headers.conf;` in every server and in every location that sets its own `add_header` (those replace inherited ones). Set the CSP per vhost.
- The OAuth authorize step is S256-only (`RequirePkceS256`). Cross-origin API access is closed; list origins in `CORS_ALLOWED_ORIGINS` only when a browser app needs it.
- Portal: never read `headers()` or `cookies()` in the locale layout, `not-found.tsx` or shared components (every page turns dynamic and loses ISR). `web/portal/e2e/a11y.spec.ts` (axe) must stay at zero violations; white text needs `bg-brand-700` or darker.
- Dev edits do not reach the node container's file watcher on Windows: after changing code, stop node, delete `web/portal/.next` and start it again.

## Production (phase 16)
- Production lives in `docker-compose.prod.yml` + `docker/prod/` + `.env.prod`, never in the dev compose file. Images contain the code; nothing is bind-mounted except TLS certs. Do not publish ports other than 80/443.
- A new env variable: add it to the `x-php` (or service) `environment:` in the prod file, to `.env.prod.example` and to the production table in ENV.md. Required secrets use `${VAR:?message}`.
- A new service needs `restart: always`, a `healthcheck`, the `logging` anchor and a `depends_on` with `condition: service_healthy`.
- The portal builds on first container start (it needs the API); bump `IMAGE_ID` per release. The php entrypoint migrates and caches config; seeders are run by hand (UPGRADE.md).
- Backups: `scripts/backup.sh` / `restore.sh`. Anything new that holds state (a volume) must be added to both.

## Real estate catalogue (phase R1)
- Locations are a flat list with `parent_id` (country > city > area, at most `Location::MAX_DEPTH` levels); clients build the tree. Slugs are unique per parent, roots included (partial unique index, because NULL parents are distinct). Tree rules live in `Requests/Concerns/ValidatesLocationTree`: reuse it for any new tree table.
- A location with children cannot be deleted (409). The public list hides a location whose ancestor is inactive.
- Property types (`key`, group residential|commercial|land) and amenities are plain Category-shaped modules; seed defaults with `firstOrCreate` so edits survive re-seeding. Permission prefixes: `locations`, `property_types`, `amenities`.
- `settings.area_unit` is `sqft|sqm`; currency fields already existed in settings.

## Properties (phase R2)
- Status is never mass-assigned or sent to PATCH (`status` is `prohibited` there). It moves only through `Services/PropertyStatusService` (`POST properties/{id}/status`), which checks `Enums/PropertyStatus::next()`, writes `property_status_history`, and sets `published_at` the first time a property becomes available. `sold` is for sale listings, `rented` for rent.
- Public vs admin: `PublicPropertyResource` (card) and `PublicPropertyDetailResource` never carry owner, agent, project, unit or history; `PropertyResource` is admin only. Public statuses are `PropertyStatus::publicValues()` (available, under_offer); other statuses answer 404 by slug.
- Search lives in `Services/PropertySearch`: filters, `tsvector` match (`search_vector`, generated column + GIN) OR a `pg_trgm` word-similarity match on the title (threshold set to 0.4 per query), ranked by `ts_rank`; radius uses `earth_box` (GiST index on `ll_to_earth(lat, lng)`) plus `earth_distance`. New filter columns need an index in the migration. lat/lng are `double precision` so the index expression needs no casts.
- `ref_code` comes from the `property_ref_seq` sequence, owned by `properties.id` so `migrate:fresh` drops it with the table.
- Media (`property_media`): image/floorplan files and PDF brochures go through `MediaService` (`store`, `storeDocument`); `video_url` stores the URL in `path`. One cover per property (partial unique index); the first image becomes the cover, only an image can be the cover, removing the cover promotes the next image.
- `agent_id` and `owner_id` are plain columns until R4 adds their tables; `project_id` and `unit_id` have foreign keys since R3 (a unit backs at most one property, unique index).

## Projects and units (phase R3)
- Developers (`developers.*`), projects (`projects.*`) and units (`project_units.*`) are separate permission prefixes. Units are shallow routes: `GET|POST projects/{project}/units`, then `GET|PATCH|DELETE units/{unit}`. Unit `code` is unique per project.
- Visitor views are subclasses (`PublicProjectResource`, `PublicProjectUnitResource`) that flip `$public`; `PublicDeveloperResource` hides email and phone. Public projects need `is_active`; the card carries `units_count` and `from_price` of available units only.
- `POST units/{unit}/publish` (`Services/ProjectUnitPublisher`, needs `project_units.update` and `properties.create`) copies the unit and the project's location/coordinates into one `draft` property (201). A second publish answers 422. The property then follows the normal status workflow.
- Deleting a developer with projects, a project with linked properties, or a unit with a property answers 409. A property's `unit_id` must belong to its `project_id` (checked in `PropertyRules`).
- Validation errors are under `error.details`; in tests use the `assertDetails([...])` macro from `ProjectsAdminTest`, not `assertJsonValidationErrors`.
- Typography: admin tokens are one step, portal tokens two steps below the Tailwind scale (`--text-*` in `packages/tokens`); use `text-sm` etc. as usual, never fixed pixel sizes.

## Agents, leads, viewings (phase R4)
- Own-records scope: `User::agent()` links a staff user to an agent profile. Without `leads.view_all` / `viewings.view_all` a user reaches only rows whose `agent_id` is their profile's (`Lead::visibleTo`, `Viewing::visibleTo`, same rule in `LeadPolicy` / `ViewingPolicy`). The seeded `agent` role has no `*.view_all`; `admin` has all. An agent who creates a lead or viewing always owns it (`agent_id` is `prohibited` for them).
- Leads: `status` and `agent_id` are never mass-assigned (`prohibited` on POST/PATCH). Status moves only through `Services/LeadStatusService` (`POST leads/{id}/status`, `Enums/LeadStatus::next()`, `lost_reason` required for lost); assignment is `POST leads/{id}/assign` (`leads.assign`). Status changes, assignments and notes are `lead_notes` rows (the timeline).
- Public enquiry: `POST public/leads` (captcha from `public/captcha`, honeypot `website`, limiter `leads`). The lead goes to the property's agent; `NewLead` (queued) is mailed to that agent, or to the site owner when unassigned. Public properties and active projects only.
- Viewings: `Services/ViewingScheduler` is the only writer. An agent cannot have two scheduled/confirmed viewings that overlap (422 on `starts_at`; the agent row is locked during the check). Status moves via `POST viewings/{id}/status` (`ViewingStatus::next()`); completed, cancelled and no_show free the calendar. Max length 8 hours.
- `properties.agent_id` has a foreign key since R4 (agent deletion with properties, leads or viewings answers 409). `owner_id` stays a plain column: there is no owners table.
- Agents: public list/detail hide the user link and admin fields (`PublicAgentResource`); `listings_count` counts available and under-offer properties. Agents are in the revalidate webhook list (tag `agents`).

## Admin: catalogue screens (phase R5)
- New CrudPage options, each used by two or more modules: `tab` on a field (the form becomes tabs; give every field a tab; a failed save jumps to the first tab with an error), `rowEndpoint` (rows edited and deleted elsewhere than the list, for shallow routes), `loadOnEdit` (fetch the row before editing when the list omits what the form needs), `rowExtra` (extra row controls: status menu, links), `optionsFrom.mapAll` (options that need the whole list, e.g. location paths).
- Money: the API holds integer minor units. Use field `type: 'money'` (typed in major units, sent as minor) and `<Money minor={...}/>` in columns; both read the currency from `GET /v1/public/settings`. Conversions live in `lib/money.ts`; never divide by 100 inline.
- Status: property status is not a form field. `PropertyStatusAction` offers the moves of `lib/propertyStatus.ts` (mirror of the API enum) and posts to `/v1/properties/{id}/status`; the API stays the authority.
- Units are not in `modules`: `unitsConfig(projectId)` (list under `/v1/projects/{id}/units`, rows at `/v1/units/{id}`) is served at `/projects/:id/units` by `pages/ProjectUnits.tsx`; projects link to it with `rowExtra`. "Publish as property" is `UnitPublishAction`.
- Module `key` is the permission prefix, so `property_types` has path `/property-types`.
- Admin tests run in the node container: `docker compose exec -T -w /app/web/admin node pnpm exec vitest run` (the host Node is too old for jsdom/undici).

## Admin: sales screens and reports (phase R6)
- CrudPage options added: `filters` (selects, text, dates sent as query params; any change returns to page 1), `editExtra` (a panel under the edit fields, e.g. `LeadPanel`; its buttons are `type="button"` because it sits inside the form), field flags `createOnly` (create form only, for things the API prohibits on PATCH), `needs` (hidden and not sent without that permission) and `lookup` (`LookupField`: search `?q=`, pick one, value is the id; use it instead of a select when the list is paginated).
- Workflow menus: `RowStatusMenu` (POST `{status}`, "Move to..."), fed by `lib/salesStatus.ts` (mirror of the API tables) or `lib/propertyStatus.ts`. Statuses render with `StatusBadge`.
- Leads: status, assignment and notes are not form fields. `LeadPanel` posts to `leads/{id}/status|assign|notes`; the timeline is `lead_notes`.
- Reports (`reports.view`, admin role only, counts every record): `GET reports/{leads|viewings|properties}?from&to` returns `{key,title,columns,rows}`; `.../export` is the same table as CSV (cells starting with `= + - @` get a leading apostrophe). Tables are built in `Services/SalesReports`; add a report there and to `KEYS`. The admin page fetches the CSV with the token and saves the blob.
- Dashboard: `new_leads` and `upcoming_viewings` follow the own-records scope; `properties` and `agents` need their `.view`.

## Portal: estate design system (phase R7)
- Tokens: `packages/tokens/estate.css` is imported by `web/portal/app/globals.css` after `portal.css` and re-points `brand` (harbour blue), `accent` (gold) and the neutrals; it adds `status-*`, `radius-card`, `radius-pill`, `shadow-card(-hover)`. The admin theme never imports it, so admin screens are unchanged. Utility names stay the same, so old pages pick the theme up.
- Components live in `web/portal/components/estate/` (`PropertyCard`, `ProjectCard`, `AgentCard`, `Tile`, `SearchBar`, `StatusBadge`, `PriceTag`, `FactList`, class recipes in `ui.ts`). They take plain data (`lib/api.ts` card types) plus `messages` and `money`; text comes from `messages.estate`. Logical utilities only.
- Prices are minor units: format with `lib/format.ts` (`formatPrice`, `formatArea`). A property cover is `{ file, url }`: use `file` when present, else the external `url`.
- Home sections are normal sections keyed `featured_properties`, `property_types`, `locations`, `projects`, `agents` (`components/sections/estate.tsx`, seeded in `SectionsModuleSeeder`). Shared `Frame`, `Arrow`, `Ctx` moved to `sections/frame.tsx`. Sections with no data render nothing.
- The search bar is a plain GET form to `/properties` (`listing_type`, `property_type`, `location`, `q`); the results page comes in R8.
- `/styleguide` (`app/[locale]/styleguide`) lists every component with sample data; it returns 404 when `NODE_ENV` is production and is noindex. Add new components there.
- E2E: `e2e/estate.spec.ts` runs at 390 and 1280 px (search, no horizontal scroll, axe).
