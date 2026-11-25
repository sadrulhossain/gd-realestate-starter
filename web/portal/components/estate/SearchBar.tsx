import type { Location, PropertyType } from '../../lib/api'
import { localePath, type Messages } from '../../lib/i18n'
import { btn, control } from './ui'

/** id -> "City > Area" for a flat location list (a broken parent link ends the chain). */
export function locationLabels(items: Location[]): { id: number; label: string }[] {
  const byId = new Map(items.map((l) => [l.id, l]))

  return items
    .map((item) => {
      const names: string[] = []
      const seen = new Set<number>()

      for (let node: Location | undefined = item; node && !seen.has(node.id); node = node.parent_id ? byId.get(node.parent_id) : undefined) {
        seen.add(node.id)
        names.unshift(node.name)
      }

      return { id: item.id, label: names.join(' > ') }
    })
    .sort((a, b) => a.label.localeCompare(b.label))
}

/**
 * The home search: a plain GET form to `/properties` (phase R8 reads these query names: `listing_type`, `property_type`,
 * `location`, `q`). No client JavaScript, so it works before hydration. Sale/rent is a radio pair styled as tabs.
 */
export function SearchBar({ locale, messages, types, locations }: { locale: string; messages: Messages; types: PropertyType[]; locations: Location[] }) {
  const s = messages.estate.search

  return (
    <form action={localePath(locale, '/properties')} method="get" role="search" aria-label={s.label} className="rounded-lg bg-white p-4 text-ink shadow-lift sm:p-5">
      <fieldset className="mb-4 flex gap-2">
        <legend className="sr-only">{s.listing}</legend>
        {(['sale', 'rent'] as const).map((value) => (
          <label key={value} className="cursor-pointer">
            <input type="radio" name="listing_type" value={value} defaultChecked={value === 'sale'} className="peer sr-only" />
            <span className="inline-block rounded-pill border border-line px-5 py-2 text-sm font-semibold text-ink-muted transition peer-checked:border-brand-700 peer-checked:bg-brand-700 peer-checked:text-white peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-brand-600">
              {value === 'sale' ? s.buy : s.rent}
            </span>
          </label>
        ))}
      </fieldset>
      <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-[1fr_1fr_1.2fr_auto]">
        <div>
          <label htmlFor="search-type" className="mb-1 block text-sm font-medium text-ink-muted">
            {s.type}
          </label>
          <select id="search-type" name="property_type" className={control} defaultValue="">
            <option value="">{s.anyType}</option>
            {types.map((t) => (
              <option key={t.id} value={t.key}>
                {t.name}
              </option>
            ))}
          </select>
        </div>
        <div>
          <label htmlFor="search-location" className="mb-1 block text-sm font-medium text-ink-muted">
            {s.location}
          </label>
          <select id="search-location" name="location" className={control} defaultValue="">
            <option value="">{s.anyLocation}</option>
            {locationLabels(locations).map((l) => (
              <option key={l.id} value={l.id}>
                {l.label}
              </option>
            ))}
          </select>
        </div>
        <div>
          <label htmlFor="search-q" className="mb-1 block text-sm font-medium text-ink-muted">
            {s.keyword}
          </label>
          <input id="search-q" name="q" type="search" maxLength={100} placeholder={s.keywordPlaceholder} className={control} />
        </div>
        <div className="flex items-end">
          <button type="submit" className={`${btn} w-full`}>
            {s.submit}
          </button>
        </div>
      </div>
    </form>
  )
}
