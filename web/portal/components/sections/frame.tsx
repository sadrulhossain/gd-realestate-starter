import type { ReactNode } from 'react'
import type { Section, Settings } from '../../lib/api'
import type { Messages } from '../../lib/i18n'

export interface Ctx {
  locale: string
  settings: Settings
  messages: Messages
  /** On its own page the section title is the page's h1 instead of an h2. */
  page?: boolean
}

export type SectionView = (props: { section: Section } & Ctx) => ReactNode | Promise<ReactNode>

export const wrap = 'mx-auto max-w-6xl px-4 py-12 sm:py-16 lg:py-20'

export function Arrow() {
  return (
    <svg viewBox="0 0 24 24" aria-hidden="true" className="ms-1.5 inline size-4 rtl:rotate-180" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
      <path d="M5 12h14M13 6l6 6-6 6" />
    </svg>
  )
}

/** A section's full-width band with its centred heading, intro text and content. `dark` is the navy band. */
export function Frame({ section, children, intro = true, page = false, dark = false, tint = false }: { section: Section; children?: ReactNode; intro?: boolean; page?: boolean; dark?: boolean; tint?: boolean }) {
  const Heading = page ? 'h1' : 'h2'

  return (
    <section id={section.key} aria-labelledby={`${section.key}-title`} className={`scroll-mt-20 ${dark ? 'relative overflow-hidden bg-night text-white' : tint ? 'bg-surface' : ''}`}>
      {dark && <div aria-hidden="true" className="pointer-events-none absolute -start-24 top-0 size-80 rounded-full bg-brand-600/30 blur-3xl" />}
      <div className={`${wrap} relative`}>
        <div className="mx-auto max-w-2xl text-center">
          <span aria-hidden="true" className="mx-auto mb-4 block h-1.5 w-12 rounded-full bg-gradient-to-r from-brand-500 to-accent-400" />
          <Heading id={`${section.key}-title`} className="font-display text-3xl font-extrabold tracking-tight text-balance sm:text-4xl">
            {section.title}
          </Heading>
          {intro && section.content && <p className={`mt-4 text-lg whitespace-pre-line ${dark ? 'text-white/75' : 'text-ink-muted'}`}>{section.content}</p>}
        </div>
        {children && <div className="mt-10 sm:mt-12">{children}</div>}
      </div>
    </section>
  )
}
