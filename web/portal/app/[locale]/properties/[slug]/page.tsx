import type { Metadata } from 'next'
import Link from 'next/link'
import { notFound } from 'next/navigation'
import { FactList } from '../../../../components/estate/FactList'
import { Gallery } from '../../../../components/estate/Gallery'
import { PriceTag } from '../../../../components/estate/PriceTag'
import { PropertyCard } from '../../../../components/estate/PropertyCard'
import { CompareButton, FavouriteButton } from '../../../../components/estate/SaveButtons'
import { StatusBadge } from '../../../../components/estate/StatusBadge'
import { btnOutline } from '../../../../components/estate/ui'
import { JsonLd } from '../../../../components/JsonLd'
import { Picture } from '../../../../components/Media'
import { getProperty, getSettings } from '../../../../lib/api'
import { currencyDigits } from '../../../../lib/format'
import { getMessages, isLocale, localePath } from '../../../../lib/i18n'
import { coverUrl, pageMetadata, propertyJsonLd } from '../../../../lib/seo'

type Props = { params: Promise<{ locale: string; slug: string }> }

async function load(locale: string, slug: string) {
  if (!isLocale(locale) || !/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(slug)) notFound()
  const found = await getProperty(locale, slug)
  if (!found) notFound()

  return found
}

// Rendered on first request, then cached (ISR) until the `properties` tag is revalidated (see the API's revalidate webhook).
export function generateStaticParams() {
  return []
}

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { locale, slug } = await params
  const [{ property: p }, settings] = await Promise.all([load(locale, slug), getSettings(locale)])

  return pageMetadata({
    locale,
    path: `/properties/${p.slug}`,
    siteName: settings.site_name,
    title: p.seo_title || p.title,
    description: p.seo_description || p.description,
    image: coverUrl(p.cover),
  })
}

const row = 'flex justify-between gap-4 border-b border-line py-2'

export default async function PropertyPage({ params }: Props) {
  const { locale, slug } = await params
  const [{ property: p, related }, settings] = await Promise.all([load(locale, slug), getSettings(locale)])
  const messages = getMessages(locale)
  const t = messages.estate.detail
  const place = [p.address_line, p.location?.name].filter(Boolean).join(', ')
  const lat = p.lat === null || p.lat === undefined ? NaN : Number(p.lat)
  const lng = p.lng === null || p.lng === undefined ? NaN : Number(p.lng)
  const mapUrl = Number.isFinite(lat) && Number.isFinite(lng) ? `https://www.openstreetmap.org/?mlat=${lat}&mlon=${lng}#map=16/${lat}/${lng}` : null
  const date = p.published_at ? new Intl.DateTimeFormat(locale, { dateStyle: 'long' }).format(new Date(p.published_at)) : null
  const files = p.media.filter((m) => m.kind === 'floorplan' || m.kind === 'brochure' || m.kind === 'video_url')
  const details: [string, string | null][] = [
    [t.ref, p.ref_code],
    [t.type, p.property_type?.name ?? null],
    [t.floor, p.floor != null ? (p.total_floors ? t.floorOf.replace('{floor}', String(p.floor)).replace('{total}', String(p.total_floors)) : String(p.floor)) : null],
    [t.facing, p.facing],
    [t.year, p.year_built ? String(p.year_built) : null],
    [t.furnishing, p.furnished ? ((messages.estate.furnishing as Record<string, string>)[p.furnished] ?? p.furnished) : null],
  ]
  const agentLink = 'inline-flex items-center rounded-pill border border-brand-300 px-4 py-2 text-sm font-semibold text-brand-700 hover:bg-brand-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600'

  return (
    <article className="mx-auto max-w-6xl px-4 py-8 sm:py-12">
      <JsonLd data={propertyJsonLd(p, settings, locale, p.price / 10 ** currencyDigits(settings.currency))} />
      <Link href={localePath(locale, '/properties')} className="text-brand-700 hover:underline">
        <span aria-hidden="true" className="inline-block rtl:rotate-180">←</span> {t.back}
      </Link>

      <div className="mt-4">
        <Gallery media={p.media} title={p.title} messages={messages} />
      </div>

      <div className="mt-8 grid gap-10 lg:grid-cols-[1fr_22rem]">
        <div className="space-y-10">
          <header className="space-y-3">
            <div className="flex flex-wrap items-center gap-2">
              <StatusBadge status={p.status} messages={messages} />
              <span className="rounded-pill bg-night px-3 py-1 text-xs font-semibold text-white">{messages.estate.listing[p.listing_type]}</span>
              {p.featured && <span className="rounded-pill bg-accent-400 px-3 py-1 text-xs font-semibold text-ink">{messages.estate.card.featured}</span>}
            </div>
            <h1 className="font-display text-3xl leading-tight font-extrabold tracking-tight text-balance sm:text-4xl">{p.title}</h1>
            {place && <p className="text-lg text-ink-muted">{place}</p>}
            <PriceTag minor={p.price} money={settings} period={p.listing_type === 'rent' ? p.rent_period : null} messages={messages} className="text-3xl" />
            <FactList beds={p.beds} baths={p.baths} area={p.area} areaUnit={p.area_unit} messages={messages} className="text-base" />
            <div className="flex flex-wrap items-center gap-3 pt-1">
              <FavouriteButton slug={p.slug} title={p.title} messages={messages} className="border border-line" />
              <CompareButton slug={p.slug} title={p.title} messages={messages} className="[&>button]:border [&>button]:border-line" />
            </div>
          </header>

          {p.description && (
            <section aria-labelledby="about-title">
              <h2 id="about-title" className="font-display text-2xl font-bold tracking-tight">
                {t.description}
              </h2>
              <p className="mt-3 max-w-prose whitespace-pre-line text-ink">{p.description}</p>
            </section>
          )}

          <section aria-labelledby="details-title">
            <h2 id="details-title" className="font-display text-2xl font-bold tracking-tight">
              {t.details}
            </h2>
            <dl className="mt-3 grid gap-x-10 sm:grid-cols-2">
              {details
                .filter(([, v]) => v)
                .map(([k, v]) => (
                  <div key={k} className={row}>
                    <dt className="text-ink-muted">{k}</dt>
                    <dd className="font-medium">{v}</dd>
                  </div>
                ))}
            </dl>
          </section>

          {p.amenities.length > 0 && (
            <section aria-labelledby="amenities-title">
              <h2 id="amenities-title" className="font-display text-2xl font-bold tracking-tight">
                {t.amenities}
              </h2>
              <ul className="mt-3 flex flex-wrap gap-2">
                {p.amenities.map((a) => (
                  <li key={a.id} className="rounded-pill border border-line bg-surface px-4 py-1.5 text-sm font-medium">
                    {a.name}
                  </li>
                ))}
              </ul>
            </section>
          )}

          {(mapUrl || files.length > 0) && (
            <section aria-labelledby="location-title">
              <h2 id="location-title" className="font-display text-2xl font-bold tracking-tight">
                {t.location}
              </h2>
              <ul className="mt-3 flex flex-wrap gap-3">
                {mapUrl && (
                  <li>
                    <a href={mapUrl} target="_blank" rel="noopener noreferrer" className={btnOutline}>
                      {t.map}
                      <span className="sr-only"> ({messages.estate.detail.opensNew})</span>
                    </a>
                  </li>
                )}
                {files.map((m) => {
                  const href = m.file?.url ?? m.url
                  if (!href) return null

                  return (
                    <li key={m.id}>
                      <a href={href} target="_blank" rel="noopener noreferrer" className={btnOutline}>
                        {(messages.estate.detail.files as Record<string, string>)[m.kind] ?? m.kind}
                        <span className="sr-only"> ({messages.estate.detail.opensNew})</span>
                      </a>
                    </li>
                  )
                })}
              </ul>
            </section>
          )}
        </div>

        <aside aria-labelledby="agent-title" className="space-y-6 lg:sticky lg:top-24 lg:self-start">
          <section className="rounded-card border border-line bg-surface p-6 shadow-card">
            <h2 id="agent-title" className="text-sm font-semibold tracking-wide text-ink-muted uppercase">
              {t.agent}
            </h2>
            {p.agent ? (
              <div className="mt-4 flex flex-col gap-4">
                <div className="flex items-center gap-4">
                  {p.agent.image ? (
                    <Picture image={p.agent.image} alt="" sizes="64px" className="size-16 rounded-full object-cover ring-4 ring-brand-100" />
                  ) : (
                    <span aria-hidden="true" className="flex size-16 items-center justify-center rounded-full bg-brand-700 font-display text-2xl font-bold text-white ring-4 ring-brand-100">
                      {p.agent.name.trim().charAt(0).toUpperCase()}
                    </span>
                  )}
                  <div>
                    <p className="font-display text-lg font-bold">{p.agent.name}</p>
                    {p.agent.title && <p className="text-sm text-ink-muted">{p.agent.title}</p>}
                  </div>
                </div>
                <ul className="flex flex-wrap gap-2">
                  {p.agent.phone && (
                    <li>
                      <a href={`tel:${p.agent.phone.replace(/[^\d+]/g, '')}`} className={agentLink}>
                        {t.callAgent}
                        <span className="sr-only"> {p.agent.name}</span>
                      </a>
                    </li>
                  )}
                  {p.agent.email && (
                    <li>
                      <a href={`mailto:${p.agent.email}?subject=${encodeURIComponent(`${p.ref_code} ${p.title}`)}`} className={agentLink}>
                        {t.emailAgent}
                        <span className="sr-only"> {p.agent.name}</span>
                      </a>
                    </li>
                  )}
                  {p.agent.whatsapp && (
                    <li>
                      <a href={`https://wa.me/${p.agent.whatsapp.replace(/\D/g, '')}`} target="_blank" rel="noopener noreferrer" className={agentLink}>
                        {t.whatsapp}
                        <span className="sr-only"> {p.agent.name} ({t.opensNew})</span>
                      </a>
                    </li>
                  )}
                </ul>
              </div>
            ) : (
              <p className="mt-3 text-ink-muted">{settings.phone ?? settings.email ?? settings.site_name}</p>
            )}
            {date && <p className="mt-5 border-t border-line pt-3 text-sm text-ink-muted">{t.published.replace('{date}', date)}</p>}
          </section>
        </aside>
      </div>

      {related.length > 0 && (
        <section aria-labelledby="related-title" className="mt-14 border-t border-line pt-10">
          <h2 id="related-title" className="font-display text-2xl font-bold tracking-tight">
            {t.related}
          </h2>
          <ul className="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            {related.map((r) => (
              <li key={r.id} className="flex">
                <div className="flex w-full flex-col">
                  <PropertyCard property={r} locale={locale} messages={messages} money={settings} />
                </div>
              </li>
            ))}
          </ul>
        </section>
      )}
    </article>
  )
}
