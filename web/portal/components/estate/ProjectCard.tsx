import Link from 'next/link'
import type { ProjectCardData } from '../../lib/api'
import { formatPrice, type Money } from '../../lib/format'
import { localePath, type Messages } from '../../lib/i18n'
import { cardBase } from './ui'

/** A development: name, developer, place, build status and, when units are on sale, the lowest price. The title is the card's link. */
export function ProjectCard({ project: p, locale, messages, money }: { project: ProjectCardData; locale: string; messages: Messages; money: Money }) {
  const e = messages.estate
  const status = (e.project as Record<string, string>)[p.status] ?? p.status

  return (
    <article className={`${cardBase} group relative flex flex-col gap-3 border-t-4 border-t-accent-400 p-5`}>
      <p className="text-xs font-semibold tracking-wide text-brand-700 uppercase">{status}</p>
      <h3 className="font-display text-xl font-bold tracking-tight text-balance">
        <Link href={localePath(locale, `/projects/${p.slug}`)} className="after:absolute after:inset-0 hover:text-brand-700">
          {p.name}
        </Link>
      </h3>
      {(p.developer || p.location) && <p className="text-sm text-ink-muted">{[p.developer?.name, p.location?.name].filter(Boolean).join(' - ')}</p>}
      <div className="mt-auto flex flex-wrap items-end justify-between gap-2 border-t border-line pt-3 text-sm">
        {p.from_price ? (
          <p>
            <span className="text-ink-muted">{e.home.projectFrom} </span>
            <span className="font-display text-lg font-extrabold text-brand-800">{formatPrice(p.from_price, money)}</span>
          </p>
        ) : (
          <span />
        )}
        {p.units_count ? <p className="text-ink-muted">{e.home.projectUnits.replace('{count}', String(p.units_count))}</p> : null}
      </div>
    </article>
  )
}
