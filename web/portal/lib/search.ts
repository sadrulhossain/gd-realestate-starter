/**
 * The property search lives in the URL (`/properties?listing_type=rent&beds_min=2&page=2`), so every result page can be
 * shared, bookmarked and crawled. Only known keys with valid values go to the API; everything else is dropped.
 */

export const SORTS = ['newest', 'price_asc', 'price_desc', 'area_desc'] as const
export type Sort = (typeof SORTS)[number]

export type RawParams = Record<string, string | string[] | undefined>

const first = (v: string | string[] | undefined): string | undefined => (Array.isArray(v) ? v[0] : v)
const int = (v: string | undefined, min = 0, max = 1_000_000_000): string | undefined => (v && /^\d+$/.test(v) && Number(v) >= min && Number(v) <= max ? String(Number(v)) : undefined)

export interface Filters {
  listing_type?: 'sale' | 'rent'
  property_type?: string
  location?: string
  q?: string
  /** Whole currency units as typed by the visitor; converted to minor units for the API. */
  price_min?: string
  price_max?: string
  beds_min?: string
  baths_min?: string
  furnished?: 'furnished' | 'semi_furnished' | 'unfurnished'
  amenities: string[]
  sort?: Sort
  page: number
}

export function parseFilters(raw: RawParams): Filters {
  const listing = first(raw.listing_type)
  const furnished = first(raw.furnished)
  const sort = first(raw.sort)
  const type = first(raw.property_type)
  const q = first(raw.q)?.trim().slice(0, 100)
  const amenities = (Array.isArray(raw.amenities) ? raw.amenities : raw.amenities ? [raw.amenities] : []).filter((a) => /^\d+$/.test(a)).slice(0, 20)

  return {
    listing_type: listing === 'sale' || listing === 'rent' ? listing : undefined,
    property_type: type && /^[a-z0-9_-]{1,60}$/i.test(type) ? type : undefined,
    location: int(first(raw.location), 1),
    q: q || undefined,
    price_min: int(first(raw.price_min)),
    price_max: int(first(raw.price_max)),
    beds_min: int(first(raw.beds_min), 0, 99),
    baths_min: int(first(raw.baths_min), 0, 99),
    furnished: furnished === 'furnished' || furnished === 'semi_furnished' || furnished === 'unfurnished' ? furnished : undefined,
    amenities,
    sort: SORTS.find((s) => s === sort),
    page: Number(int(first(raw.page), 1, 10_000) ?? 1),
  }
}

/** Query string for the portal's own URL (what the visitor sees). Defaults are omitted. */
export function toUrlQuery(f: Filters, page = f.page): URLSearchParams {
  const p = new URLSearchParams()
  for (const key of ['listing_type', 'property_type', 'location', 'q', 'price_min', 'price_max', 'beds_min', 'baths_min', 'furnished', 'sort'] as const) {
    if (f[key]) p.set(key, f[key])
  }
  for (const id of f.amenities) p.append('amenities', id)
  if (page > 1) p.set('page', String(page))

  return p
}

/** Query string for the API. Prices are typed in whole units but stored in minor units, so they are scaled here. */
export function toApiQuery(f: Filters, minorUnits: number): URLSearchParams {
  const p = toUrlQuery(f)
  for (const key of ['price_min', 'price_max'] as const) {
    if (f[key]) p.set(key, String(Number(f[key]) * 10 ** minorUnits))
  }
  // The API names its list amenities[] ; keep the URL's repeated key but send the bracketed form.
  p.delete('amenities')
  for (const id of f.amenities) p.append('amenities[]', id)
  // sort 'newest' is the API default; the portal's offered sorts map one to one.
  if (f.price_min && f.price_max && Number(f.price_min) > Number(f.price_max)) p.delete('price_max')

  return p
}

export const hasFilters = (f: Filters): boolean => Object.entries(f).some(([k, v]) => k !== 'page' && k !== 'sort' && (Array.isArray(v) ? v.length > 0 : Boolean(v)))
