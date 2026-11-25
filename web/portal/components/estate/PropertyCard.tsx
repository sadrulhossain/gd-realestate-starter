import Link from 'next/link'
import type { Media, PropertyCardData } from '../../lib/api'
import type { Money } from '../../lib/format'
import { localePath, type Messages } from '../../lib/i18n'
import { Picture } from '../Media'
import { FactList } from './FactList'
import { PriceTag } from './PriceTag'
import { CompareButton, FavouriteButton } from './SaveButtons'
import { StatusBadge } from './StatusBadge'
import { cardBase } from './ui'

/**
 * A listing in a grid: cover photo, status and listing chips, price, title, place and key facts. The title is a stretched
 * link (::after covers the card), so the card is one click target; the favourite and compare buttons sit above it (z-10).
 */
export function PropertyCard({ property: p, locale, messages, money }: { property: PropertyCardData; locale: string; messages: Messages; money: Money }) {
  const e = messages.estate
  const cover: Media | null = p.cover?.file ?? (p.cover?.url ? { url: p.cover.url, webp: {} } : null)
  const place = [p.address_line, p.location?.name].filter(Boolean).join(', ')

  return (
    <article className={`${cardBase} group relative flex flex-col`}>
      <div className="relative aspect-[4/3] overflow-hidden bg-brand-50">
        {cover ? (
          <Picture image={cover} sizes="(min-width: 1024px) 33vw, (min-width: 640px) 50vw, 100vw" className="size-full object-cover transition duration-500 group-hover:scale-105" />
        ) : (
          <div className="flex size-full items-center justify-center bg-gradient-to-br from-brand-100 to-brand-200 text-brand-700">
            <span className="text-sm font-medium">{e.card.noPhoto}</span>
          </div>
        )}
        <div className="absolute start-3 top-3 flex flex-wrap gap-2">
          <StatusBadge status={p.status} messages={messages} />
          {p.featured && <span className="inline-block rounded-pill bg-accent-400 px-3 py-1 text-xs font-semibold text-ink">{e.card.featured}</span>}
        </div>
        <FavouriteButton slug={p.slug} title={p.title} messages={messages} className="absolute end-3 bottom-3" />
        <CompareButton slug={p.slug} title={p.title} messages={messages} className="absolute start-3 bottom-3" />
        <span className="absolute end-3 top-3 rounded-pill bg-night/85 px-3 py-1 text-xs font-semibold text-white">{e.listing[p.listing_type]}</span>
      </div>
      <div className="flex flex-1 flex-col gap-2 p-5">
        <PriceTag minor={p.price} money={money} period={p.listing_type === 'rent' ? p.rent_period : null} messages={messages} className="text-2xl" />
        <h3 className="font-display text-lg font-bold tracking-tight text-balance">
          <Link href={localePath(locale, `/properties/${p.slug}`)} className="after:absolute after:inset-0 hover:text-brand-700">
            {p.title}
          </Link>
        </h3>
        {place && <p className="text-sm text-ink-muted">{place}</p>}
        <FactList beds={p.beds} baths={p.baths} area={p.area} areaUnit={p.area_unit} messages={messages} className="mt-auto border-t border-line pt-3" />
      </div>
    </article>
  )
}
