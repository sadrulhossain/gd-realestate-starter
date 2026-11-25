/**
 * Class recipes shared by the estate components. Whole strings on purpose: Tailwind only generates utilities it can
 * find as literals. Logical properties only (ms-, pe-, start-, end-) so right-to-left locales work.
 */

/** Primary action: solid brand fill, white text (brand-600 or darker keeps AA contrast). */
export const btn = 'inline-flex items-center justify-center gap-2 rounded-pill bg-brand-600 px-6 py-3 font-semibold text-white shadow-soft transition hover:-translate-y-0.5 hover:bg-brand-700 hover:shadow-lift focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600'

/** Secondary action: outlined, for use on light backgrounds. */
export const btnOutline = 'inline-flex items-center justify-center gap-2 rounded-pill border border-brand-300 bg-white px-6 py-3 font-semibold text-brand-700 transition hover:border-brand-500 hover:bg-brand-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600'

/** Gold call to action: dark text on gold (never white on gold). */
export const btnGold = 'inline-flex items-center justify-center gap-2 rounded-pill bg-accent-400 px-6 py-3 font-semibold text-ink shadow-soft transition hover:-translate-y-0.5 hover:bg-accent-300 hover:shadow-lift focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-600'

/** The white card every listing, project and agent sits in. Add `group relative` where a stretched link is used. */
export const cardBase = 'overflow-hidden rounded-card border border-line bg-surface shadow-card transition duration-300 hover:-translate-y-1 hover:shadow-card-hover'

/** Form controls inside the search bar. */
export const control = 'w-full rounded-md border border-line bg-white px-3 py-2.5 text-ink placeholder:text-ink-muted focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-brand-600'
