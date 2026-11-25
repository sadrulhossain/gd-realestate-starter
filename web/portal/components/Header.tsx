import Link from 'next/link'
import type { Section, Settings } from '../lib/api'
import { defaultLocale, localePath, locales, type Messages } from '../lib/i18n'
import { SECTION_PAGES } from '../lib/section-page'
import { LanguageSwitcher } from './LanguageSwitcher'
import { MobileNav } from './MobileNav'
import { NavDropdown } from './NavDropdown'
import { SiteLogo } from './SiteLogo'

/** Sections that only make sense as part of the home page (their content is not a page of its own): no menu entry. */
const HOME_ONLY = new Set(['featured_properties', 'property_types'])

/** Sticky glass header: logo, one link per visible section (the hero is the page top, contact has its own button), Blog, Contact. */
export function Header({ locale, settings, sections, messages }: { locale: string; settings: Settings; sections: Section[]; messages: Messages }) {
  const home = localePath(locale)
  const links = [
    ...sections
      .filter((s) => s.key !== 'hero' && s.key !== 'contact' && !HOME_ONLY.has(s.key))
      .map((s) => ({ key: s.key, label: s.title, href: SECTION_PAGES[s.key] ? localePath(locale, SECTION_PAGES[s.key]) : `${home}#${s.key}` })),
  ]
  const blog = localePath(locale, '/blog')
  const properties = localePath(locale, '/properties')
  const favourites = localePath(locale, '/favourites')
  const contact = localePath(locale, '/contact')
  const switcher = locales.length > 1 && <LanguageSwitcher current={locale} label={messages.language.label} locales={locales} unprefixed={defaultLocale} />

  return (
    <header className="sticky top-0 z-40 border-b border-line/70 bg-surface/80 backdrop-blur-lg">
      <div className="relative mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3">
        <Link href={home} className="min-w-0 shrink">
          <SiteLogo settings={settings} />
        </Link>

        <nav aria-label={messages.nav.label} className="hidden items-center gap-1 md:flex">
          <Link href={home} className="rounded-full px-3 py-2 text-[0.95rem] font-medium text-ink-muted transition-colors hover:bg-brand-50 hover:text-brand-700">
            {messages.nav.home}
          </Link>
          {links.length > 0 && (
            <NavDropdown label={messages.nav.explore}>
              {links.map((l) => (
                <li key={l.key}>
                  <Link href={l.href} className="block rounded-md px-3 py-2.5 font-medium text-ink hover:bg-brand-50 hover:text-brand-700">
                    {l.label}
                  </Link>
                </li>
              ))}
            </NavDropdown>
          )}
          <Link href={properties} className="rounded-full px-3 py-2 text-[0.95rem] font-medium text-ink-muted transition-colors hover:bg-brand-50 hover:text-brand-700">
            {messages.nav.properties}
          </Link>
          <Link href={blog} className="rounded-full px-3 py-2 text-[0.95rem] font-medium text-ink-muted transition-colors hover:bg-brand-50 hover:text-brand-700">
            {messages.nav.blog}
          </Link>
          <Link href={favourites} className="rounded-full px-3 py-2 text-[0.95rem] font-medium text-ink-muted transition-colors hover:bg-brand-50 hover:text-brand-700">
            {messages.estate.saved.nav}
          </Link>
          {switcher}
          <Link href={contact} className="ms-2 rounded-full bg-brand-600 px-5 py-2.5 text-[0.95rem] font-semibold text-white shadow-soft transition hover:-translate-y-0.5 hover:bg-brand-700 hover:shadow-lift">
            {messages.nav.contact}
          </Link>
        </nav>

        <MobileNav label={messages.nav.label}>
          <nav aria-label={messages.nav.label} className="flex flex-col gap-1">
            <Link href={home} className="rounded-md px-4 py-3 text-lg font-medium text-ink hover:bg-brand-50">
              {messages.nav.home}
            </Link>
            {links.length > 0 && <p className="px-4 pt-1 text-xs font-semibold tracking-widest text-ink-muted uppercase">{messages.nav.explore}</p>}
            {links.map((l) => (
              <Link key={l.key} href={l.href} className="rounded-md py-3 ps-8 pe-4 text-lg font-medium text-ink hover:bg-brand-50">
                {l.label}
              </Link>
            ))}
            <Link href={properties} className="rounded-md px-4 py-3 text-lg font-medium text-ink hover:bg-brand-50">
              {messages.nav.properties}
            </Link>
            <Link href={blog} className="rounded-md px-4 py-3 text-lg font-medium text-ink hover:bg-brand-50">
              {messages.nav.blog}
            </Link>
            <Link href={favourites} className="rounded-md px-4 py-3 text-lg font-medium text-ink hover:bg-brand-50">
              {messages.estate.saved.nav}
            </Link>
            <Link href={contact} className="mt-2 rounded-full bg-brand-600 px-5 py-3 text-center text-lg font-semibold text-white">
              {messages.nav.contact}
            </Link>
            {switcher && <div className="mt-3 flex justify-center">{switcher}</div>}
          </nav>
        </MobileNav>
      </div>
    </header>
  )
}
