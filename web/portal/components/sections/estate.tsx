import Link from 'next/link'
import { getAgents, getLocations, getProjects, getProperties, getPropertyTypes } from '../../lib/api'
import { localePath } from '../../lib/i18n'
import { locationSlugPath } from '../../lib/locations'
import { AgentCard } from '../estate/AgentCard'
import { ProjectCard } from '../estate/ProjectCard'
import { PropertyCard } from '../estate/PropertyCard'
import { Tile } from '../estate/Tile'
import { btnOutline } from '../estate/ui'
import { Arrow, Frame, type SectionView } from './frame'

/** Home sections of the real estate site. Each is keyed like any section (`featured_properties` ...), so admins can hide and reorder them. */

/** Featured listings, newest first; with none featured the latest listings fill the grid. */
export const FeaturedProperties: SectionView = async ({ section, locale, settings, messages, page }) => {
  const featured = await getProperties(locale, { featured: 1, per_page: 6 })
  const items = featured.length > 0 ? featured : await getProperties(locale, { per_page: 6 })

  return (
    <Frame section={section} page={page}>
      {items.length === 0 ? (
        <p className="text-center text-ink-muted">{messages.estate.home.emptyProperties}</p>
      ) : (
        <>
          <ul className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            {items.map((p) => (
              <li key={p.id} className="flex">
                <div className="flex w-full flex-col">
                  <PropertyCard property={p} locale={locale} messages={messages} money={settings} />
                </div>
              </li>
            ))}
          </ul>
          <p className="mt-10 text-center">
            <Link href={localePath(locale, '/properties')} className={btnOutline}>
              {messages.estate.home.viewAll}
              <Arrow />
            </Link>
          </p>
        </>
      )}
    </Frame>
  )
}

/** One tile per property type; each opens the search filtered to that type. */
export const PropertyTypes: SectionView = async ({ section, locale, messages, page }) => {
  const types = await getPropertyTypes(locale)
  if (types.length === 0) return null

  return (
    <Frame section={section} page={page} tint>
      <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {types.map((t) => (
          <li key={t.id}>
            <Tile href={`${localePath(locale, '/properties')}?property_type=${encodeURIComponent(t.key)}`} name={t.name} hint={messages.estate.home.browseType.replace('{name}', t.name)} />
          </li>
        ))}
      </ul>
    </Frame>
  )
}

/** Popular places: cities and areas (countries only when there is nothing deeper), eight at most. */
export const Locations: SectionView = async ({ section, locale, messages, page }) => {
  const all = await getLocations(locale)
  const nested = all.filter((l) => l.parent_id !== null)
  const places = (nested.length > 0 ? nested : all).slice(0, 8)
  if (places.length === 0) return null

  const name = new Map(all.map((l) => [l.id, l.name]))

  return (
    <Frame section={section} page={page}>
      <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {places.map((l) => (
          <li key={l.id}>
            <Tile
              href={localePath(locale, `/locations/${locationSlugPath(all, l)}`)}
              name={l.name}
              hint={l.parent_id ? (name.get(l.parent_id) ?? undefined) : messages.estate.home.browseLocation.replace('{name}', l.name)}
            />
          </li>
        ))}
      </ul>
    </Frame>
  )
}

/** New developments: up to three project cards. Hidden when there are none. */
export const Projects: SectionView = async ({ section, locale, settings, messages, page }) => {
  const projects = (await getProjects(locale)).slice(0, 3)
  if (projects.length === 0) return null

  return (
    <Frame section={section} page={page} dark>
      <ul className="grid gap-6 md:grid-cols-3">
        {projects.map((p) => (
          <li key={p.id} className="flex">
            <div className="flex w-full flex-col text-ink">
              <ProjectCard project={p} locale={locale} messages={messages} money={settings} />
            </div>
          </li>
        ))}
      </ul>
    </Frame>
  )
}

/** The people behind the listings: up to four agents. Hidden when there are none. */
export const Agents: SectionView = async ({ section, locale, messages, page }) => {
  const agents = (await getAgents(locale)).slice(0, 4)
  if (agents.length === 0) return null

  return (
    <Frame section={section} page={page} tint>
      <ul className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
        {agents.map((a) => (
          <li key={a.id} className="flex">
            <div className="flex w-full flex-col">
              <AgentCard agent={a} messages={messages} locale={locale} />
            </div>
          </li>
        ))}
      </ul>
    </Frame>
  )
}
