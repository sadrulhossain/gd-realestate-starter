# Theme baseline (R0)

Inherited from the business-site `main`; R0 changes no styling. R7 redesigns the **portal only** through a portal-scoped `estate` token layer; admin tokens and components stay untouched.

## Tokens (`packages/tokens`)
- `base.css`: `--font-sans`, `--font-mono`, breakpoints `sm`, `md`, `lg`, `xl`. Shared by both themes.
- `admin.css`: Tailwind `@theme` with `--color-primary-50..900`, `--color-canvas`, `--color-surface(-muted)`, `--color-ink(-muted)`, `--color-line(-strong)`, `--color-link`, status colors (`danger`, `success`, `warning`, `info` and `*-solid`), sidebar colors (`sidebar-glass`, `sidebar-line`, `sidebar-text`), `--radius-sm..2xl`, `--shadow-sm..lg`, `--text-sm/base`, tints. Each theme resets `--color-*`, so only its own palette exists.
- `portal.css`: `--color-brand-50..900` and `--color-accent-300..600` on top of `base.css`. R7 adds the `estate` layer here, extending (not replacing) these.

## Admin components (`web/admin/src/components`, never restyled by R7)
BilingualField, Brand, ConfirmDialog, CrudPage (config-driven), DataTable, FormField, GalleryUploader, Icon, IconButton, ImagePanel, RichText, Splash, ThemeToggle, Toast, ToggleCell. Layout in `web/admin/src/layout`.

## Portal components (`web/portal/components`, restyled in R7)
ContactForm, Footer, Header, HoursList, JsonLd, LanguageSwitcher, Media, MobileNav, NavDropdown, NotFoundContent, PricingPlans, SiteLogo, SocialIcon, and the section renderer in `sections/index.tsx`.

## Rule
Admin screenshots of three pages must be identical before and after R7 (Playwright). Portal styling changes only through the portal token layer and the R7 components.
