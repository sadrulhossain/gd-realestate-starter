import { ApiError, createClient } from '@gd/api-client'
import { defaultLocale } from './i18n'

/**
 * Server-side API access with classic Next caching: every public read is cached for REVALIDATE seconds and tagged with
 * the table name the API's webhook sends (/api/revalidate), so an edit in the admin refreshes just that content.
 */
export const REVALIDATE = 3600

/** "/v1/public/team" -> "team_members", the tag ContentObserver sends (the model's table name). */
const TAGS: Record<string, string> = {
  settings: 'settings',
  sections: 'sections',
  faqs: 'faqs',
  pricing: 'pricings',
  services: 'services',
  testimonials: 'testimonials',
  team: 'team_members',
  gallery: 'gallery_items',
  categories: 'categories',
  posts: 'posts',
  properties: 'properties',
  amenities: 'amenities',
  'property-types': 'property_types',
  locations: 'locations',
  projects: 'projects',
  agents: 'agents',
}

export function tagFor(url: string): string[] {
  const resource = new URL(url).pathname.split('/public/')[1]?.split('/')[0]
  const tag = resource ? TAGS[resource] : undefined

  return tag ? [tag] : []
}

const baseUrl = () => process.env.API_INTERNAL_URL ?? 'http://api.localhost/api'

/** One client per locale: `?lang=` makes the API return a single language per field (the second falls back to the first). */
export function apiFor(locale: string) {
  return createClient({
    baseUrl: baseUrl(),
    lang: locale === defaultLocale ? undefined : locale,
    fetch: (input, init) => fetch(input, { ...init, next: { revalidate: REVALIDATE, tags: tagFor(String(input)) } }),
  })
}

export interface Media {
  url: string
  webp: Record<string, string>
}

export interface Settings {
  site_name: string
  tagline: string | null
  logo?: Media | null
  currency?: string | null
  currency_symbol?: string | null
  favicon?: Media | null
  address: string | null
  phone: string | null
  email: string | null
  hours: Record<string, { open: string | null; close: string | null; closed: boolean }> | null
  social_links: Record<string, string | null>
}

export interface Section {
  id: number
  key: string
  title: string
  content: string | null
  image: Media | null
  sort_order: number
  is_visible: boolean
}

export interface Service {
  id: number
  title: string
  description: string | null
  image: Media | null
}

export interface Testimonial {
  id: number
  author_name: string
  author_role: string | null
  quote: string
  image: Media | null
}

export interface TeamMember {
  id: number
  name: string
  role: string
  bio: string | null
  image: Media | null
}

export interface GalleryItem {
  id: number
  title: string | null
  caption: string | null
  image: Media | null
}

export interface Pricing {
  id: number
  service_id: number
  service?: { id: number; title: string }
  name: string
  price: string
  unit: string | null
  description: string | null
  features: string | null
  is_featured: boolean
}

export interface Faq {
  id: number
  question: string
  answer: string
}

type Listing<T> = { data: T[] }

export async function getSettings(locale: string): Promise<Settings> {
  return ((await apiFor(locale).request('GET', '/v1/public/settings')) as { data: Settings }).data
}

/** Visible sections in display order. The API already filters; `is_visible` is checked again so a stale cache cannot show a hidden one. */
export async function getSections(locale: string): Promise<Section[]> {
  const rows = ((await apiFor(locale).request('GET', '/v1/public/sections')) as Listing<Section>).data

  return rows.filter((section) => section.is_visible)
}

export interface Category {
  id: number
  name: string
  slug: string
}

export interface Post {
  id: number
  title: string
  slug: string
  excerpt: string | null
  /** Sanitised HTML (the API's HtmlSanitizer), safe to render as is. */
  body: string | null
  image: Media | null
  category: Category | null
  seo_title: string | null
  seo_description: string | null
  published_at: string | null
  updated_at: string
}

export interface PostPage {
  posts: Post[]
  page: number
  lastPage: number
}

/** One page of published posts (12 per page), optionally of one category. */
export async function getPosts(locale: string, { page = 1, category }: { page?: number; category?: string } = {}): Promise<PostPage> {
  const body = (await apiFor(locale).request('GET', '/v1/public/posts', { query: { page, category } })) as {
    data: Post[]
    meta?: { current_page: number; last_page: number }
  }

  return { posts: body.data, page: body.meta?.current_page ?? 1, lastPage: body.meta?.last_page ?? 1 }
}

/** One published post by slug; null when it is a draft, scheduled or gone. */
export async function getPost(locale: string, slug: string): Promise<Post | null> {
  try {
    return ((await apiFor(locale).request('GET', `/v1/public/posts/${encodeURIComponent(slug)}`)) as { data: Post }).data
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) return null
    throw error
  }
}

export async function getCategories(locale: string): Promise<Category[]> {
  return ((await apiFor(locale).request('GET', '/v1/public/categories')) as Listing<Category>).data
}

/** One visible service; null when it is hidden or gone (the API answers 404). */
export async function getService(locale: string, id: string): Promise<Service | null> {
  try {
    return ((await apiFor(locale).request('GET', `/v1/public/services/${encodeURIComponent(id)}`)) as { data: Service }).data
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) return null
    throw error
  }
}

/** Visible prices of visible services, in service order; optionally those of one service. */
export async function getPricing(locale: string, serviceId?: number | string): Promise<Pricing[]> {
  const query = serviceId === undefined ? '' : `?service_id=${encodeURIComponent(String(serviceId))}`

  return ((await apiFor(locale).request('GET', `/v1/public/pricing${query}`)) as Listing<Pricing>).data
}

export async function getList<T>(locale: string, resource: 'services' | 'testimonials' | 'team' | 'gallery' | 'faqs'): Promise<T[]> {
  return ((await apiFor(locale).request('GET', `/v1/public/${resource}`)) as Listing<T>).data
}

// --- Real estate (R7) ---

export interface PropertyType {
  id: number
  key: string
  name: string
  group: string
}

export interface Location {
  id: number
  parent_id: number | null
  name: string
  slug: string
}

/** The card view of a listing (`PublicPropertyResource`): never carries owner, agent or workflow data. */
export interface PropertyCardData {
  id: number
  ref_code: string
  slug: string
  title: string
  listing_type: 'sale' | 'rent'
  status: string
  featured: boolean
  property_type: PropertyType | null
  location: Location | null
  address_line: string | null
  price: number
  rent_period: 'month' | 'year' | 'day' | null
  area: string | number | null
  area_unit: string | null
  beds: number | null
  baths: number | null
  /** A cover is an uploaded file (with WebP widths) or an external URL. */
  cover: { file: Media | null; url: string | null } | null
}

export interface ProjectCardData {
  id: number
  slug: string
  name: string
  status: string
  location: Location | null
  developer: { name: string } | null
  units_count?: number
  from_price?: number
}

export interface AgentCardData {
  bio?: string | null
  license_no?: string | null
  id: number
  slug: string
  name: string
  title: string | null
  phone: string | null
  whatsapp?: string | null
  email: string | null
  image: Media | null
  listings_count?: number
}

export async function getProperties(locale: string, query: Record<string, string | number> = {}): Promise<PropertyCardData[]> {
  const qs = new URLSearchParams(Object.entries(query).map(([k, v]) => [k, String(v)])).toString()

  return ((await apiFor(locale).request('GET', `/v1/public/properties${qs ? `?${qs}` : ''}`)) as Listing<PropertyCardData>).data
}

export async function getPropertyTypes(locale: string): Promise<PropertyType[]> {
  return ((await apiFor(locale).request('GET', '/v1/public/property-types')) as Listing<PropertyType>).data
}

export async function getLocations(locale: string): Promise<Location[]> {
  return ((await apiFor(locale).request('GET', '/v1/public/locations')) as Listing<Location>).data
}

export async function getProjects(locale: string): Promise<ProjectCardData[]> {
  return ((await apiFor(locale).request('GET', '/v1/public/projects')) as Listing<ProjectCardData>).data
}

export async function getAgents(locale: string): Promise<AgentCardData[]> {
  return ((await apiFor(locale).request('GET', '/v1/public/agents')) as Listing<AgentCardData>).data
}

export interface Amenity {
  id: number
  name: string
  icon: string | null
  group: string | null
}

export interface PropertyMediaItem {
  id: number
  kind: string
  file: Media | null
  url: string | null
  is_cover: boolean
}

export interface PropertyDetailData extends PropertyCardData {
  description: string | null
  floor: number | null
  total_floors: number | null
  facing: string | null
  year_built: number | null
  furnished: string | null
  lat: string | number | null
  lng: string | number | null
  seo_title: string | null
  seo_description: string | null
  published_at: string | null
  amenities: Amenity[]
  media: PropertyMediaItem[]
  agent: AgentCardData | null
}

export interface PropertyPage {
  properties: PropertyCardData[]
  page: number
  lastPage: number
  total: number
}

/** One page of the public search (12 per page). `query` is already validated: see lib/search.ts. */
export async function searchProperties(locale: string, query: URLSearchParams): Promise<PropertyPage> {
  const qs = query.toString()
  const body = (await apiFor(locale).request('GET', `/v1/public/properties${qs ? `?${qs}` : ''}`)) as {
    data: PropertyCardData[]
    meta?: { current_page: number; last_page: number; total: number }
  }

  return { properties: body.data, page: body.meta?.current_page ?? 1, lastPage: body.meta?.last_page ?? 1, total: body.meta?.total ?? body.data.length }
}

/** A property by slug with its similar listings; null when it is not public (draft, sold, rented) or gone. */
export async function getProperty(locale: string, slug: string): Promise<{ property: PropertyDetailData; related: PropertyCardData[] } | null> {
  try {
    const body = (await apiFor(locale).request('GET', `/v1/public/properties/${encodeURIComponent(slug)}`)) as { data: PropertyDetailData; related?: PropertyCardData[] }

    return { property: body.data, related: body.related ?? [] }
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) return null
    throw error
  }
}

export async function getAmenities(locale: string): Promise<Amenity[]> {
  return ((await apiFor(locale).request('GET', '/v1/public/amenities')) as Listing<Amenity>).data
}

/** Just these listings (saved and compared lists), in the order asked; slugs that are no longer public drop out. */
export async function getPropertiesBySlugs(locale: string, slugs: string[]): Promise<PropertyCardData[]> {
  const unique = [...new Set(slugs)].slice(0, 48)
  if (unique.length === 0) return []

  const qs = unique.map((s) => `slugs[]=${encodeURIComponent(s)}`).join('&')
  const rows = ((await apiFor(locale).request('GET', `/v1/public/properties?${qs}&per_page=48`)) as Listing<PropertyCardData>).data

  return unique.flatMap((slug) => rows.filter((r) => r.slug === slug))
}

// --- Agents, locations, projects (R9) ---

export interface ProjectUnitData {
  id: number
  code: string | null
  name: string | null
  property_type: PropertyType | null
  beds: number | null
  baths: number | null
  area: string | number | null
  area_unit: string | null
  floor: number | null
  price: number
  status: string
}

export interface ProjectDetailData extends ProjectCardData {
  description: string | null
  address_line: string | null
  lat: string | number | null
  lng: string | number | null
  handover_date: string | null
  featured: boolean
  seo_title: string | null
  seo_description: string | null
  developer: { name: string; website?: string | null } | null
  units: ProjectUnitData[]
}

export async function getAgent(locale: string, slug: string): Promise<AgentCardData | null> {
  try {
    return ((await apiFor(locale).request('GET', `/v1/public/agents/${encodeURIComponent(slug)}`)) as { data: AgentCardData }).data
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) return null
    throw error
  }
}

export async function getProject(locale: string, slug: string): Promise<ProjectDetailData | null> {
  try {
    return ((await apiFor(locale).request('GET', `/v1/public/projects/${encodeURIComponent(slug)}`)) as { data: ProjectDetailData }).data
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) return null
    throw error
  }
}

export interface ProjectPage {
  projects: ProjectCardData[]
  page: number
  lastPage: number
}

/** One page of active projects (12 per page), featured first. */
export async function getProjectPage(locale: string, page = 1): Promise<ProjectPage> {
  const body = (await apiFor(locale).request('GET', `/v1/public/projects?page=${page}`)) as {
    data: ProjectCardData[]
    meta?: { current_page: number; last_page: number }
  }

  return { projects: body.data, page: body.meta?.current_page ?? 1, lastPage: body.meta?.last_page ?? 1 }
}
