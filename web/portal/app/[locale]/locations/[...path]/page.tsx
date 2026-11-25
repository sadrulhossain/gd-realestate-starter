import type { Metadata } from 'next'
import Link from 'next/link'
import { notFound } from 'next/navigation'
import { PropertyCard } from '../../../../components/estate/PropertyCard'
import { Tile } from '../../../../components/estate/Tile'
import { btnOutline } from '../../../../components/estate/ui'
import { JsonLd } from '../../../../components/JsonLd'
import { getLocations, getSettings, searchProperties } from '../../../../lib/api'
import { getMessages, localePath } from '../../../../lib/i18n'
import { chainOf, childrenOf, locationSlugPath, resolveLocation } from '../../../../lib/locations'
import { requireSection } from '../../../../lib/offer'
import { pageMetadata, propertyListJsonLd } from '../../../../lib/seo'

type Props = { params: Promise<{ locale: string; path: string[] }> }

async function load(locale: string, path: string[]) {
  await requireSection(locale, 'locations')
  if (path.length > 6 || !path.every((s) => /^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(s))) notFound()
  const all = await getLocations(locale)
  const location = resolveLocation(all, path)
  if (!location) notFound()

  return { all, location }
}

// Rendered on first request, then cached (ISR) until the `locations` or `properties` tag is revalidated.
export function generateStaticParams() {
  return []
}

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { locale, path } = await params
  const [{ all, location }, settings] = await Promise.all([load(locale, path), getSettings(locale)])
  const t = getMessages(locale).estate.locations

  return pageMetadata({
    locale,
    path: `/locations/${locationSlugPath(all, location)}`,
    siteName: settings.site_name,
    title: t.inLocation.replace('{name}', location.name),
    description: t.introLocation.replace('{name}', location.name),
  })
}

export default async function LocationPage({ params }: Props) {
  const { locale, path } = await params
  const [{ all, location }, settings] = await Promise.all([load(locale, path), getSettings(locale)])
  const messages = getMessages(locale)
  const t = messages.estate.locations
  const chain = chainOf(all, location)
  const children = childrenOf(all, location)
  const listings = await searchProperties(locale, new URLSearchParams({ location: String(location.id), per_page: '12' }))
  const href = (l: (typeof all)[number]) => localePath(locale, `/locations/${locationSlugPath(all, l)}`)

  return (
    <div className="mx-auto max-w-6xl px-4 py-10 sm:py-14">
      {listings.properties.length > 0 && <JsonLd data={propertyListJsonLd(listings.properties, locale, t.inLocation.replace('{name}', location.name))} />}
      <nav aria-label={t.title}>
        <ol className="flex flex-wrap items-center gap-x-2 text-sm text-ink-muted">
          <li>
            <Link href={localePath(locale, '/locations')} className="text-brand-700 hover:underline">
              {t.all}
            </Link>
          </li>
          {chain.map((l, i) => (
            <li key={l.id} className="flex items-center gap-x-2">
              <span aria-hidden="true">/</span>
              {i === chain.length - 1 ? (
                <span aria-current="page">{l.name}</span>
              ) : (
                <Link href={href(l)} className="text-brand-700 hover:underline">
                  {l.name}
                </Link>
              )}
            </li>
          ))}
        </ol>
      </nav>

      <h1 className="mt-4 font-display text-3xl font-extrabold tracking-tight text-balance sm:text-4xl">{t.inLocation.replace('{name}', location.name)}</h1>
      <p className="mt-2 max-w-2xl text-ink-muted">{t.introLocation.replace('{name}', location.name)}</p>

      {children.length > 0 && (
        <section aria-labelledby="areas-title" className="mt-10">
          <h2 id="areas-title" className="font-display text-2xl font-bold tracking-tight">
            {t.areas.replace('{name}', location.name)}
          </h2>
          <ul className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {children.map((child) => (
              <li key={child.id}>
                <Tile href={href(child)} name={child.name} hint={t.inLocation.replace('{name}', child.name)} />
              </li>
            ))}
          </ul>
        </section>
      )}

      <section aria-labelledby="loc-listings" className="mt-12 border-t border-line pt-10">
        <h2 id="loc-listings" className="font-display text-2xl font-bold tracking-tight">
          {messages.estate.list.title}
        </h2>
        {listings.properties.length === 0 ? (
          <p className="mt-4 text-ink-muted">{t.noListings}</p>
        ) : (
          <>
            <ul className="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
              {listings.properties.map((p) => (
                <li key={p.id} className="flex">
                  <div className="flex w-full flex-col">
                    <PropertyCard property={p} locale={locale} messages={messages} money={settings} />
                  </div>
                </li>
              ))}
            </ul>
            <p className="mt-8 text-center">
              <Link href={`${localePath(locale, '/properties')}?location=${location.id}`} className={btnOutline}>
                {t.viewAll.replace('{name}', location.name)}
              </Link>
            </p>
          </>
        )}
      </section>
    </div>
  )
}
