import type { MetadataRoute } from 'next'
import { getAgents, getList, getLocations, getPosts, getProjectPage, getSections, searchProperties, type Service } from '../lib/api'
import { defaultLocale, localePath, locales } from '../lib/i18n'
import { locationSlugPath } from '../lib/locations'
import { SECTION_PAGES } from '../lib/section-page'
import { absolute } from '../lib/seo'

/**
 * Every public page in every locale, each with its hreflang alternates. Pages that come from a section are listed only
 * while that section is visible. Built from the same cached, tagged fetches as the pages, so an admin edit refreshes it.
 */
export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const sections = await getSections(defaultLocale)
  const services = sections.some((s) => s.key === 'services') ? await getList<Service>(defaultLocale, 'services') : []
  const posts = await allPosts()
  const properties = await allProperties()
  const offered = (key: string) => sections.some((s) => s.key === key)
  const agents = offered('agents') ? await getAgents(defaultLocale) : []
  const locations = offered('locations') ? await getLocations(defaultLocale) : []
  const projects = offered('projects') ? await allProjects() : []

  const paths: { path: string; modified?: string }[] = [
    { path: '/' },
    ...sections.flatMap((s) => (SECTION_PAGES[s.key] ? [{ path: SECTION_PAGES[s.key]! }] : [])),
    { path: '/blog' },
    { path: '/properties' },
    { path: '/contact' },
    { path: '/privacy' },
    { path: '/terms' },
    ...services.map((s) => ({ path: `/services/${s.id}` })),
    ...posts.map((p) => ({ path: `/blog/${p.slug}`, modified: p.updated_at })),
    ...properties.map((p) => ({ path: `/properties/${p.slug}` })),
    ...agents.map((a) => ({ path: `/agents/${a.slug}` })),
    ...locations.map((l) => ({ path: `/locations/${locationSlugPath(locations, l)}` })),
    ...projects.map((p) => ({ path: `/projects/${p.slug}` })),
  ]

  return paths.flatMap(({ path, modified }) =>
    locales.map((locale) => ({
      url: absolute(localePath(locale, path)),
      lastModified: modified ? new Date(modified) : undefined,
      alternates: { languages: Object.fromEntries(locales.map((code) => [code, absolute(localePath(code, path))])) },
    })),
  )
}

async function allPosts() {
  const first = await getPosts(defaultLocale)
  const rest = await Promise.all(Array.from({ length: Math.max(0, first.lastPage - 1) }, (_, i) => getPosts(defaultLocale, { page: i + 2 })))

  return [first, ...rest].flatMap((page) => page.posts)
}

/** Every public property (the search is 12 per page, so walk the pages). Capped so a huge catalogue cannot stall the sitemap. */
async function allProperties() {
  const first = await searchProperties(defaultLocale, new URLSearchParams({ per_page: '48' }))
  const pages = Math.min(first.lastPage, 20)
  const rest = await Promise.all(Array.from({ length: Math.max(0, pages - 1) }, (_, i) => searchProperties(defaultLocale, new URLSearchParams({ per_page: '48', page: String(i + 2) }))))

  return [first, ...rest].flatMap((page) => page.properties)
}

async function allProjects() {
  const first = await getProjectPage(defaultLocale)
  const rest = await Promise.all(Array.from({ length: Math.max(0, Math.min(first.lastPage, 20) - 1) }, (_, i) => getProjectPage(defaultLocale, i + 2)))

  return [first, ...rest].flatMap((page) => page.projects)
}
