import Link from 'next/link'
import type { AgentCardData } from '../../lib/api'
import { localePath, type Messages } from '../../lib/i18n'
import { Picture } from '../Media'
import { cardBase } from './ui'

/**
 * An agent: photo (or initial), name, job title, listing count and a phone link. With `locale` the name links to the
 * profile (a stretched link, so the card is one click target; the phone link stays above it).
 */
export function AgentCard({ agent: a, messages, locale }: { agent: AgentCardData; messages: Messages; locale?: string }) {
  return (
    <article className={`${cardBase} group relative flex flex-col items-center gap-3 p-6 text-center`}>
      {a.image ? (
        <Picture image={a.image} alt={a.name} sizes="96px" className="size-24 rounded-full object-cover shadow-soft ring-4 ring-brand-100" />
      ) : (
        <span aria-hidden="true" className="flex size-24 items-center justify-center rounded-full bg-brand-700 font-display text-3xl font-bold text-white ring-4 ring-brand-100">
          {a.name.trim().charAt(0).toUpperCase()}
        </span>
      )}
      <div>
        <h3 className="font-display text-lg font-bold tracking-tight">
          {locale ? (
            <Link href={localePath(locale, `/agents/${a.slug}`)} className="after:absolute after:inset-0 hover:text-brand-700">
              {a.name}
            </Link>
          ) : (
            a.name
          )}
        </h3>
        {a.title && <p className="text-sm text-ink-muted">{a.title}</p>}
      </div>
      {a.listings_count ? <p className="text-sm font-medium text-brand-700">{messages.estate.home.agentListings.replace('{count}', String(a.listings_count))}</p> : null}
      {a.phone && (
        <a href={`tel:${a.phone.replace(/[^\d+]/g, '')}`} className="relative z-10 text-sm font-semibold text-brand-700 hover:underline">
          {a.phone}
        </a>
      )}
    </article>
  )
}
