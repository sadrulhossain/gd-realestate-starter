import type { Metadata } from 'next'
import { notFound } from 'next/navigation'
import { FilterForm } from '../../../components/estate/FilterForm'
import { Pagination } from '../../../components/estate/Pagination'
import { PropertyCard } from '../../../components/estate/PropertyCard'
import { JsonLd } from '../../../components/JsonLd'
import { getAmenities, getLocations, getPropertyTypes, getSettings, searchProperties } from '../../../lib/api'
import { currencyDigits } from '../../../lib/format'
import { getMessages, isLocale, localePath } from '../../../lib/i18n'
import { propertyListJsonLd, pageMetadata } from '../../../lib/seo'
import { hasFilters, parseFilters, toApiQuery, toUrlQuery, type RawParams } from '../../../lib/search'

type Props = { params: Promise<{ locale: string }>; searchParams: Promise<RawParams> }

export async function generateMetadata({ params, searchParams }: Props): Promise<Metadata> {
  const { locale } = await params
  if (!isLocale(locale)) return {}
  const filters = parseFilters(await searchParams)
  const [settings, messages] = [await getSettings(locale), getMessages(locale)]
  const t = messages.estate.list

  // Every filtered or later page canonicalises to the plain list (views of one list, not landing pages) and stays out of the index.
  const meta = pageMetadata({ locale, path: '/properties', siteName: settings.site_name, title: `${t.title}${filters.page > 1 ? ` (${filters.page})` : ''}`, description: t.intro })

  return hasFilters(filters) || filters.page > 1 ? { ...meta, robots: { index: false, follow: true } } : meta
}

/** `/properties?listing_type=rent&beds_min=2&page=2`: the whole search state is in the URL. */
export default async function Properties({ params, searchParams }: Props) {
  const { locale } = await params
  if (!isLocale(locale)) notFound()

  const filters = parseFilters(await searchParams)
  const messages = getMessages(locale)
  const t = messages.estate.list
  const settings = await getSettings(locale)
  const digits = currencyDigits(settings.currency)

  const [result, types, locations, amenities] = await Promise.all([
    searchProperties(locale, toApiQuery(filters, digits)),
    getPropertyTypes(locale),
    getLocations(locale),
    getAmenities(locale),
  ])

  const hrefFor = (page: number) => {
    const query = toUrlQuery(filters, page)

    return `${localePath(locale, '/properties')}${query.size ? `?${query}` : ''}`
  }
  const count = result.total === 1 ? t.countOne : t.count.replace('{count}', result.total.toLocaleString('en'))

  return (
    <div className="mx-auto max-w-6xl px-4 py-10 sm:py-14">
      {result.properties.length > 0 && <JsonLd data={propertyListJsonLd(result.properties, locale, t.title)} />}
      <h1 className="font-display text-3xl font-extrabold tracking-tight text-balance sm:text-4xl">{t.title}</h1>
      <p className="mt-2 text-ink-muted">{t.intro}</p>

      <div className="mt-6">
        <FilterForm locale={locale} messages={messages} filters={filters} types={types} locations={locations} amenities={amenities} currencySymbol={settings.currency_symbol || settings.currency || ''} />
      </div>

      <section aria-labelledby="results-title" className="mt-10">
        <h2 id="results-title" className="sr-only">
          {t.results}
        </h2>
        <p role="status" className="mb-4 font-medium text-ink-muted">
          {count}
        </p>
        {result.properties.length === 0 ? (
          <div className="rounded-card border border-dashed border-line bg-surface p-10 text-center">
            <p className="font-display text-xl font-bold">{t.empty}</p>
            <p className="mt-2 text-ink-muted">{t.emptyHint}</p>
          </div>
        ) : (
          <ul className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            {result.properties.map((p) => (
              <li key={p.id} className="flex">
                <div className="flex w-full flex-col">
                  <PropertyCard property={p} locale={locale} messages={messages} money={settings} />
                </div>
              </li>
            ))}
          </ul>
        )}
        <Pagination page={result.page} lastPage={result.lastPage} hrefFor={hrefFor} messages={messages} />
      </section>
    </div>
  )
}
