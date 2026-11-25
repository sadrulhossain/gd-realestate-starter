import type { Amenity, Location, PropertyType } from '../../lib/api'
import { localePath, type Messages } from '../../lib/i18n'
import { SORTS, type Filters } from '../../lib/search'
import { locationLabels } from './SearchBar'
import { btn, btnOutline, control } from './ui'

const label = 'mb-1 block text-sm font-medium text-ink-muted'
const counts = ['1', '2', '3', '4', '5']

/**
 * The full filter set as a plain GET form back to /properties: no client JavaScript, so it works before hydration and the
 * result page URL is always the shareable state. Submitting resets to page 1 (page is not a field).
 */
export function FilterForm({ locale, messages, filters, types, locations, amenities, currencySymbol }: { locale: string; messages: Messages; filters: Filters; types: PropertyType[]; locations: Location[]; amenities: Amenity[]; currencySymbol: string }) {
  const s = messages.estate.search
  const l = messages.estate.list

  return (
    <form action={localePath(locale, '/properties')} method="get" role="search" aria-label={s.label} className="rounded-card border border-line bg-surface p-4 shadow-card sm:p-5">
      <fieldset className="mb-4 flex gap-2">
        <legend className="sr-only">{s.listing}</legend>
        {(['sale', 'rent'] as const).map((value) => (
          <label key={value} className="cursor-pointer">
            <input type="radio" name="listing_type" value={value} defaultChecked={filters.listing_type === value} className="peer sr-only" />
            <span className="inline-block rounded-pill border border-line px-5 py-2 text-sm font-semibold text-ink-muted transition peer-checked:border-brand-700 peer-checked:bg-brand-700 peer-checked:text-white peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-brand-600">
              {value === 'sale' ? s.buy : s.rent}
            </span>
          </label>
        ))}
      </fieldset>

      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div>
          <label htmlFor="f-q" className={label}>
            {s.keyword}
          </label>
          <input id="f-q" name="q" type="search" maxLength={100} defaultValue={filters.q} placeholder={s.keywordPlaceholder} className={control} />
        </div>
        <div>
          <label htmlFor="f-type" className={label}>
            {s.type}
          </label>
          <select id="f-type" name="property_type" defaultValue={filters.property_type ?? ''} className={control}>
            <option value="">{s.anyType}</option>
            {types.map((t) => (
              <option key={t.id} value={t.key}>
                {t.name}
              </option>
            ))}
          </select>
        </div>
        <div>
          <label htmlFor="f-location" className={label}>
            {s.location}
          </label>
          <select id="f-location" name="location" defaultValue={filters.location ?? ''} className={control}>
            <option value="">{s.anyLocation}</option>
            {locationLabels(locations).map((x) => (
              <option key={x.id} value={x.id}>
                {x.label}
              </option>
            ))}
          </select>
        </div>
        <div>
          <label htmlFor="f-sort" className={label}>
            {l.sort}
          </label>
          <select id="f-sort" name="sort" defaultValue={filters.sort ?? 'newest'} className={control}>
            {SORTS.map((o) => (
              <option key={o} value={o}>
                {(messages.estate.sort as Record<string, string>)[o]}
              </option>
            ))}
          </select>
        </div>
        <div>
          <label htmlFor="f-pmin" className={label}>
            {l.minPrice} ({currencySymbol})
          </label>
          <input id="f-pmin" name="price_min" type="number" inputMode="numeric" min={0} step={1} defaultValue={filters.price_min} className={control} />
        </div>
        <div>
          <label htmlFor="f-pmax" className={label}>
            {l.maxPrice} ({currencySymbol})
          </label>
          <input id="f-pmax" name="price_max" type="number" inputMode="numeric" min={0} step={1} defaultValue={filters.price_max} className={control} />
        </div>
        <div>
          <label htmlFor="f-beds" className={label}>
            {l.beds}
          </label>
          <select id="f-beds" name="beds_min" defaultValue={filters.beds_min ?? ''} className={control}>
            <option value="">{l.any}</option>
            {counts.map((n) => (
              <option key={n} value={n}>
                {l.orMore.replace('{n}', n)}
              </option>
            ))}
          </select>
        </div>
        <div>
          <label htmlFor="f-baths" className={label}>
            {l.baths}
          </label>
          <select id="f-baths" name="baths_min" defaultValue={filters.baths_min ?? ''} className={control}>
            <option value="">{l.any}</option>
            {counts.map((n) => (
              <option key={n} value={n}>
                {l.orMore.replace('{n}', n)}
              </option>
            ))}
          </select>
        </div>
      </div>

      <details className="mt-4 rounded-md border border-line px-4 py-3" open={filters.amenities.length > 0 || Boolean(filters.furnished)}>
        <summary className="cursor-pointer font-medium text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600">
          {l.furnished} / {l.amenities}
        </summary>
        <div className="mt-3 grid gap-4 md:grid-cols-[14rem_1fr]">
          <div>
            <label htmlFor="f-furnished" className={label}>
              {l.furnished}
            </label>
            <select id="f-furnished" name="furnished" defaultValue={filters.furnished ?? ''} className={control}>
              <option value="">{l.any}</option>
              {(['furnished', 'semi_furnished', 'unfurnished'] as const).map((v) => (
                <option key={v} value={v}>
                  {messages.estate.furnishing[v]}
                </option>
              ))}
            </select>
          </div>
          {amenities.length > 0 && (
            <fieldset>
              <legend className={label}>{l.amenities}</legend>
              <ul className="grid grid-cols-2 gap-x-4 gap-y-2 sm:grid-cols-3">
                {amenities.map((a) => (
                  <li key={a.id}>
                    <label className="flex cursor-pointer items-center gap-2 text-sm">
                      <input type="checkbox" name="amenities" value={a.id} defaultChecked={filters.amenities.includes(String(a.id))} className="size-4 accent-brand-700" />
                      {a.name}
                    </label>
                  </li>
                ))}
              </ul>
            </fieldset>
          )}
        </div>
      </details>

      <div className="mt-5 flex flex-wrap items-center gap-3">
        <button type="submit" className={btn}>
          {l.apply}
        </button>
        <a href={localePath(locale, '/properties')} className={btnOutline}>
          {l.clear}
        </a>
      </div>
    </form>
  )
}
