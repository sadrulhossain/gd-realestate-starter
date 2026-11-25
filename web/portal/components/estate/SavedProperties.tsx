'use client'

import Link from 'next/link'
import { useEffect, useState } from 'react'
import type { PropertyCardData } from '../../lib/api'
import { formatArea, formatPrice, type Money } from '../../lib/format'
import { localePath, type Messages } from '../../lib/i18n'
import { clearSaved, removeSaved, useSaved, type ListName } from '../../lib/saved'
import { PropertyCard } from './PropertyCard'
import { btn, btnOutline } from './ui'

type Loaded = { key: string; properties: PropertyCardData[]; money: Money }

/** Fetches the cards for the saved slugs. Slugs the server no longer returns (sold, unpublished) are dropped from storage. */
function useSavedProperties(list: ListName, locale: string) {
  const slugs = useSaved(list)
  const key = slugs.join(',')
  const [loaded, setLoaded] = useState<Loaded | null>(null)
  const [failed, setFailed] = useState(false)
  const [dropped, setDropped] = useState(false)

  useEffect(() => {
    if (!key) return

    const controller = new AbortController()
    fetch(`/api/properties?locale=${encodeURIComponent(locale)}&slugs=${encodeURIComponent(key)}`, { signal: controller.signal })
      .then((r) => (r.ok ? (r.json() as Promise<{ properties: PropertyCardData[]; money: Money }>) : Promise.reject(new Error(String(r.status)))))
      .then((body) => {
        setFailed(false)
        setLoaded({ key, properties: body.properties, money: body.money })

        const alive = new Set(body.properties.map((p) => p.slug))
        const gone = key.split(',').filter((s) => !alive.has(s))
        if (gone.length > 0) {
          setDropped(true)
          gone.forEach((s) => removeSaved(list, s))
        }
      })
      .catch((error: unknown) => {
        if (!(error instanceof DOMException && error.name === 'AbortError')) setFailed(true)
      })

    return () => controller.abort()
  }, [key, list, locale])

  return { slugs, loaded: key === '' ? { key, properties: [], money: {} } : loaded, loading: key !== '' && loaded?.key !== key && !failed, failed, dropped }
}

function Empty({ text, locale, messages }: { text: string; locale: string; messages: Messages }) {
  return (
    <div className="rounded-card border border-dashed border-line bg-surface p-10 text-center">
      <p className="font-display text-xl font-bold">{text}</p>
      <Link href={localePath(locale, '/properties')} className={`${btn} mt-5`}>
        {messages.estate.saved.browse}
      </Link>
    </div>
  )
}

/** The favourites page body: the saved listings as cards. */
export function FavouritesList({ locale, messages }: { locale: string; messages: Messages }) {
  const t = messages.estate.saved
  const { slugs, loaded, loading, failed, dropped } = useSavedProperties('favourites', locale)

  return (
    <div>
      <p role="status" className="mb-4 text-ink-muted">
        {dropped ? t.unavailable : loading ? t.loading : ''}
      </p>
      {failed && <p className="text-danger">{t.failed}</p>}
      {slugs.length === 0 && !loading ? (
        <Empty text={t.favouritesEmpty} locale={locale} messages={messages} />
      ) : (
        loaded && (
          <ul className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            {loaded.properties
              .filter((p) => slugs.includes(p.slug))
              .map((p) => (
                <li key={p.id} className="flex">
                  <div className="flex w-full flex-col">
                    <PropertyCard property={p} locale={locale} messages={messages} money={loaded.money} />
                  </div>
                </li>
              ))}
          </ul>
        )
      )}
    </div>
  )
}

/** The compare page body: up to three listings in columns, one row per fact. Narrow screens scroll the table sideways. */
export function CompareTable({ locale, messages }: { locale: string; messages: Messages }) {
  const t = messages.estate.saved
  const { slugs, loaded, loading, failed, dropped } = useSavedProperties('compare', locale)
  const items = loaded ? slugs.flatMap((s) => loaded.properties.filter((p) => p.slug === s)) : []

  if (slugs.length === 0 && !loading) return <Empty text={t.compareEmpty} locale={locale} messages={messages} />

  const money = loaded?.money ?? {}
  const rows: [string, (p: PropertyCardData) => string][] = [
    [t.row.price, (p) => (p.price ? `${formatPrice(p.price, money)}${p.listing_type === 'rent' && p.rent_period ? ` ${(messages.estate.period as Record<string, string>)[p.rent_period]}` : ''}` : messages.estate.card.priceOnRequest)],
    [t.row.listing, (p) => messages.estate.listing[p.listing_type]],
    [t.row.status, (p) => (messages.estate.status as Record<string, string>)[p.status] ?? p.status],
    [t.row.type, (p) => p.property_type?.name ?? '-'],
    [t.row.location, (p) => [p.address_line, p.location?.name].filter(Boolean).join(', ') || '-'],
    [t.row.beds, (p) => (p.beds != null ? String(p.beds) : '-')],
    [t.row.baths, (p) => (p.baths != null ? String(p.baths) : '-')],
    [t.row.area, (p) => formatArea(p.area, p.area_unit, messages.estate.units) ?? '-'],
  ]

  return (
    <div>
      <p role="status" className="mb-4 text-ink-muted">
        {dropped ? t.unavailable : loading ? t.loading : ''}
      </p>
      {failed && <p className="text-danger">{t.failed}</p>}
      {items.length > 0 && (
        <>
          <div role="region" aria-label={t.compareTitle} tabIndex={0} className="overflow-x-auto rounded-card border border-line bg-surface shadow-card focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600">
            <table className="w-full min-w-[34rem] border-collapse text-start">
              <caption className="sr-only">{t.compareTitle}</caption>
              <thead>
                <tr>
                  <td className="w-32 border-b border-line p-4" />
                  {items.map((p) => (
                    <th key={p.id} scope="col" className="border-b border-line p-4 text-start align-top">
                      <Link href={localePath(locale, `/properties/${p.slug}`)} className="font-display text-lg font-bold text-brand-800 hover:underline">
                        {p.title}
                      </Link>
                      <button type="button" onClick={() => removeSaved('compare', p.slug)} className="mt-2 block text-sm font-medium text-ink-muted underline underline-offset-4 hover:text-danger focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600">
                        {t.remove}
                        <span className="sr-only"> {p.title}</span>
                      </button>
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {rows.map(([label, value]) => (
                  <tr key={label} className="odd:bg-canvas">
                    <th scope="row" className="p-4 text-start text-sm font-medium text-ink-muted">
                      {label}
                    </th>
                    {items.map((p) => (
                      <td key={p.id} className="p-4 font-medium">
                        {value(p)}
                      </td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <button type="button" onClick={() => clearSaved('compare')} className={`${btnOutline} mt-6`}>
            {t.clear}
          </button>
        </>
      )}
    </div>
  )
}
