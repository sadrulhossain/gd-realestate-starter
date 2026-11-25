import type { Metadata } from 'next'
import { notFound } from 'next/navigation'
import type { ReactNode } from 'react'
import { AgentCard } from '../../../components/estate/AgentCard'
import { FactList } from '../../../components/estate/FactList'
import { PriceTag } from '../../../components/estate/PriceTag'
import { ProjectCard } from '../../../components/estate/ProjectCard'
import { PropertyCard } from '../../../components/estate/PropertyCard'
import { SearchBar } from '../../../components/estate/SearchBar'
import { StatusBadge } from '../../../components/estate/StatusBadge'
import { Tile } from '../../../components/estate/Tile'
import { btn, btnGold, btnOutline } from '../../../components/estate/ui'
import type { AgentCardData, Location, ProjectCardData, PropertyCardData, PropertyType } from '../../../lib/api'
import { getMessages, isLocale } from '../../../lib/i18n'

export const metadata: Metadata = { title: 'Style guide', robots: { index: false, follow: false } }

// Static sample data in the shape of the public API (see lib/api.ts): no network, so this page never depends on seeded content.
const money = { currency: 'USD', currency_symbol: '$' }
const type: PropertyType = { id: 1, key: 'apartment', name: 'Apartment', group: 'residential' }
const area: Location = { id: 3, parent_id: 2, name: 'Gulshan', slug: 'gulshan' }
const property = (over: Partial<PropertyCardData>): PropertyCardData => ({
  id: 1,
  ref_code: 'P-000001',
  slug: 'lakeview-apartment',
  title: 'Lakeview apartment with a long title that wraps onto two lines',
  listing_type: 'sale',
  status: 'available',
  featured: true,
  property_type: type,
  location: area,
  address_line: 'Road 12',
  price: 18500000,
  rent_period: null,
  area: 1450,
  area_unit: 'sqft',
  beds: 3,
  baths: 2,
  cover: null,
  ...over,
})
const project: ProjectCardData = { id: 1, slug: 'harbour-residences', name: 'Harbour Residences', status: 'under_construction', location: area, developer: { name: 'Northwind Developments' }, units_count: 24, from_price: 9500000 }
const agent: AgentCardData = { id: 1, slug: 'amina-rahman', name: 'Amina Rahman', title: 'Senior agent', phone: '+880 1700 000000', email: null, image: null, listings_count: 12 }
const types: PropertyType[] = [type, { id: 2, key: 'villa', name: 'Villa', group: 'residential' }, { id: 3, key: 'office', name: 'Office', group: 'commercial' }]
const places: Location[] = [{ id: 1, parent_id: null, name: 'Bangladesh', slug: 'bangladesh' }, { id: 2, parent_id: 1, name: 'Dhaka', slug: 'dhaka' }, area]

// Full class names on purpose: Tailwind only generates utilities it can find as literal strings.
const brand = ['bg-brand-50', 'bg-brand-100', 'bg-brand-200', 'bg-brand-300', 'bg-brand-400', 'bg-brand-500', 'bg-brand-600', 'bg-brand-700', 'bg-brand-800', 'bg-brand-900'] as const
const accent = ['bg-accent-300', 'bg-accent-400', 'bg-accent-500', 'bg-accent-600'] as const
const neutrals = [
  ['canvas', 'bg-canvas'],
  ['surface', 'bg-surface'],
  ['line', 'bg-line'],
  ['ink', 'bg-ink'],
  ['ink-muted', 'bg-ink-muted'],
  ['night', 'bg-night'],
  ['night-soft', 'bg-night-soft'],
] as const
const statuses = ['available', 'under_offer', 'sold', 'rented'] as const

function Block({ id, title, children, doc, code }: { id: string; title: string; children: ReactNode; doc: ReactNode; code?: string }) {
  return (
    <section id={id} aria-labelledby={`${id}-title`} className="scroll-mt-24 border-t border-line py-10">
      <h2 id={`${id}-title`} className="font-display text-2xl font-extrabold tracking-tight">
        {title}
      </h2>
      <p className="mt-2 max-w-3xl text-ink-muted">{doc}</p>
      <div className="mt-6">{children}</div>
      {code && (
        <pre className="mt-6 overflow-x-auto rounded-md bg-night p-4 text-sm text-brand-100">
          <code>{code}</code>
        </pre>
      )}
    </section>
  )
}

const Swatch = ({ cls, name }: { cls: string; name: string }) => (
  <li className="w-20">
    <span className={`block h-14 rounded-md border border-line ${cls}`} />
    <span className="mt-1 block text-xs text-ink-muted">{name}</span>
  </li>
)

/** The estate design system, live. Development only: production answers 404 (and the page is noindex anyway). */
export default async function Styleguide({ params }: { params: Promise<{ locale: string }> }) {
  if (process.env.NODE_ENV === 'production') notFound()

  const { locale } = await params
  if (!isLocale(locale)) notFound()
  const messages = getMessages(locale)

  return (
    <div className="mx-auto max-w-6xl px-4 py-12">
      <header className="space-y-3">
        <h1 className="font-display text-4xl font-extrabold tracking-tight">Estate style guide</h1>
        <p className="max-w-3xl text-lg text-ink-muted">
          The portal&apos;s real estate look. Tokens live in <code>packages/tokens/estate.css</code> (loaded after <code>portal.css</code>, portal only); components in{' '}
          <code>web/portal/components/estate</code>. Copy these shapes, do not invent new ones. This page exists in development only.
        </p>
      </header>

      <Block id="colour" title="Colour" doc="Harbour blue is the brand, gold is the accent, slate neutrals carry text. White text is safe on brand-600 and darker; text on gold is always ink.">
        <ul className="flex flex-wrap gap-3" aria-label="Brand scale">
          {brand.map((c) => (
            <Swatch key={c} cls={c} name={c.replace('bg-', '')} />
          ))}
        </ul>
        <ul className="mt-4 flex flex-wrap gap-3" aria-label="Accent scale">
          {accent.map((c) => (
            <Swatch key={c} cls={c} name={c.replace('bg-', '')} />
          ))}
        </ul>
        <ul className="mt-4 flex flex-wrap gap-3" aria-label="Neutrals">
          {neutrals.map(([name, cls]) => (
            <Swatch key={name} cls={cls} name={name} />
          ))}
        </ul>
      </Block>

      <Block id="type" title="Type, radius and shadow" doc="Display headings use font-display, extra bold, tight tracking. Corners: rounded-md controls, rounded-card cards, rounded-pill chips and buttons. Cards rest on shadow-card and lift to shadow-card-hover.">
        <p className="font-display text-4xl font-extrabold tracking-tight">Display 4xl</p>
        <p className="font-display text-2xl font-bold tracking-tight">Heading 2xl</p>
        <p className="text-lg">Body lg. Lead text sits one step above body.</p>
        <p className="text-sm text-ink-muted">Small muted text for hints and meta.</p>
        <div className="mt-6 flex flex-wrap gap-4">
          <div className="rounded-card bg-surface p-6 shadow-card">shadow-card</div>
          <div className="rounded-card bg-surface p-6 shadow-card-hover">shadow-card-hover</div>
          <div className="rounded-pill bg-brand-100 px-6 py-3">rounded-pill</div>
        </div>
      </Block>

      <Block id="buttons" title="Buttons" doc="btn is the one primary action per view, btnOutline the quiet alternative, btnGold the call to action on dark bands. All three have a visible focus ring." code={`import { btn, btnOutline, btnGold } from '@/components/estate/ui'\n<a className={btn}>Search</a>`}>
        <div className="flex flex-wrap gap-3">
          <a href="#colour" className={btn}>
            Primary
          </a>
          <a href="#colour" className={btnOutline}>
            Outline
          </a>
          <span className="rounded-card bg-night p-3">
            <a href="#colour" className={btnGold}>
              Gold on dark
            </a>
          </span>
        </div>
      </Block>

      <Block id="status" title="StatusBadge" doc="A listing's status as a chip; the word is always shown. Props: status (available, under_offer, sold, rented; anything else renders neutral), messages." code={`<StatusBadge status="under_offer" messages={messages} />`}>
        <div className="flex flex-wrap gap-3">
          {statuses.map((s) => (
            <StatusBadge key={s} status={s} messages={messages} />
          ))}
        </div>
      </Block>

      <Block id="price" title="PriceTag and FactList" doc="Prices are integer minor units; PriceTag formats them with the site currency and adds the rent period. FactList skips facts that are missing and renders nothing when all are." code={`<PriceTag minor={18500000} money={settings} messages={messages} />\n<FactList beds={3} baths={2} area={1450} areaUnit="sqft" messages={messages} />`}>
        <div className="flex flex-wrap items-end gap-8">
          <PriceTag minor={18500000} money={money} messages={messages} className="text-3xl" />
          <PriceTag minor={85000} money={money} period="month" messages={messages} className="text-3xl" />
          <PriceTag minor={0} money={money} messages={messages} className="text-xl" />
        </div>
        <FactList beds={3} baths={2} area={1450} areaUnit="sqft" messages={messages} className="mt-6 text-base" />
      </Block>

      <Block id="property-card" title="PropertyCard" doc="One listing in a grid. The whole card is a single link (the title); the cover falls back to a tinted panel. Props: property (PropertyCardData), locale, messages, money." code={`<PropertyCard property={p} locale={locale} messages={messages} money={settings} />`}>
        <ul className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          <li className="flex">
            <div className="flex w-full flex-col">
              <PropertyCard property={property({})} locale={locale} messages={messages} money={money} />
            </div>
          </li>
          <li className="flex">
            <div className="flex w-full flex-col">
              <PropertyCard property={property({ id: 2, slug: 'city-flat-to-rent', title: 'City flat', listing_type: 'rent', rent_period: 'month', price: 85000, status: 'under_offer', featured: false, beds: 2, baths: 1, area: 900 })} locale={locale} messages={messages} money={money} />
            </div>
          </li>
          <li className="flex">
            <div className="flex w-full flex-col">
              <PropertyCard property={property({ id: 3, slug: 'plot', title: 'Corner plot', status: 'sold', featured: false, beds: null, baths: null, area: 5, area_unit: 'sqm', location: null, address_line: null })} locale={locale} messages={messages} money={money} />
            </div>
          </li>
        </ul>
      </Block>

      <Block id="tiles" title="Tile, ProjectCard, AgentCard" doc="Browse tiles for types and places, a development card with its lowest unit price, and an agent card. Agent and project detail pages arrive in later phases." code={`<Tile href={url} name="Apartment" hint="Browse Apartment" />`}>
        <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <li>
            <Tile href="#tiles" name="Apartment" hint="Browse Apartment" />
          </li>
          <li>
            <Tile href="#tiles" name="Gulshan" hint="Dhaka" />
          </li>
        </ul>
        <ul className="mt-6 grid gap-6 md:grid-cols-3">
          <li className="flex">
            <div className="flex w-full flex-col">
              <ProjectCard project={project} locale={locale} messages={messages} money={money} />
            </div>
          </li>
          <li className="flex">
            <div className="flex w-full flex-col">
              <AgentCard agent={agent} messages={messages} />
            </div>
          </li>
        </ul>
      </Block>

      <Block id="search" title="SearchBar" doc="The home search: a plain GET form to /properties with listing_type, property_type, location and q. It needs no JavaScript. Props: locale, messages, types, locations." code={`<SearchBar locale={locale} messages={messages} types={types} locations={locations} />`}>
        <div className="rounded-card bg-night p-6">
          <SearchBar locale={locale} messages={messages} types={types} locations={places} />
        </div>
      </Block>
    </div>
  )
}
