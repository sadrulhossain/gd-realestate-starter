import type { Metadata } from 'next'
import Link from 'next/link'
import { notFound } from 'next/navigation'
import { PropertyCard } from '../../../../components/estate/PropertyCard'
import { JsonLd } from '../../../../components/JsonLd'
import { Picture } from '../../../../components/Media'
import { getAgent, getSettings, searchProperties } from '../../../../lib/api'
import { getMessages, localePath } from '../../../../lib/i18n'
import { requireSection } from '../../../../lib/offer'
import { agentJsonLd, pageMetadata } from '../../../../lib/seo'

type Props = { params: Promise<{ locale: string; slug: string }> }

async function load(locale: string, slug: string) {
  await requireSection(locale, 'agents')
  if (!/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(slug)) notFound()
  const agent = await getAgent(locale, slug)
  if (!agent) notFound()

  return agent
}

// Rendered on first request, then cached (ISR) until the `agents` or `properties` tag is revalidated.
export function generateStaticParams() {
  return []
}

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { locale, slug } = await params
  const [agent, settings] = await Promise.all([load(locale, slug), getSettings(locale)])

  return pageMetadata({ locale, path: `/agents/${agent.slug}`, siteName: settings.site_name, title: agent.name, description: agent.bio || agent.title, image: agent.image?.url })
}

export default async function AgentPage({ params }: Props) {
  const { locale, slug } = await params
  const [agent, settings] = await Promise.all([load(locale, slug), getSettings(locale)])
  const messages = getMessages(locale)
  const t = messages.estate.agents
  const listings = await searchProperties(locale, new URLSearchParams({ agent: String(agent.id), per_page: '12' }))
  const link = 'inline-flex items-center rounded-pill border border-brand-300 px-4 py-2 text-sm font-semibold text-brand-700 hover:bg-brand-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600'

  return (
    <article className="mx-auto max-w-6xl px-4 py-10 sm:py-14">
      <JsonLd data={agentJsonLd(agent, settings, locale)} />
      <Link href={localePath(locale, '/agents')} className="text-brand-700 hover:underline">
        <span aria-hidden="true" className="inline-block rtl:rotate-180">←</span> {t.all}
      </Link>

      <header className="mt-6 flex flex-col items-start gap-6 sm:flex-row sm:items-center">
        {agent.image ? (
          <Picture image={agent.image} alt="" sizes="160px" loading="eager" className="size-32 shrink-0 rounded-full object-cover shadow-soft ring-4 ring-brand-100 sm:size-40" />
        ) : (
          <span aria-hidden="true" className="flex size-32 shrink-0 items-center justify-center rounded-full bg-brand-700 font-display text-5xl font-bold text-white ring-4 ring-brand-100 sm:size-40">
            {agent.name.trim().charAt(0).toUpperCase()}
          </span>
        )}
        <div className="space-y-2">
          <h1 className="font-display text-3xl font-extrabold tracking-tight text-balance sm:text-4xl">{agent.name}</h1>
          {agent.title && <p className="text-lg text-ink-muted">{agent.title}</p>}
          {agent.license_no && <p className="text-sm text-ink-muted">{t.license.replace('{no}', agent.license_no)}</p>}
          <ul aria-label={t.contact} className="flex flex-wrap gap-2 pt-1">
            {agent.phone && (
              <li>
                <a href={`tel:${agent.phone.replace(/[^\d+]/g, '')}`} className={link}>
                  {agent.phone}
                </a>
              </li>
            )}
            {agent.email && (
              <li>
                <a href={`mailto:${agent.email}`} className={link}>
                  {agent.email}
                </a>
              </li>
            )}
            {agent.whatsapp && (
              <li>
                <a href={`https://wa.me/${agent.whatsapp.replace(/\D/g, '')}`} target="_blank" rel="noopener noreferrer" className={link}>
                  {messages.estate.detail.whatsapp}
                  <span className="sr-only"> ({messages.estate.detail.opensNew})</span>
                </a>
              </li>
            )}
          </ul>
        </div>
      </header>

      {agent.bio && (
        <section aria-labelledby="bio-title" className="mt-10">
          <h2 id="bio-title" className="font-display text-2xl font-bold tracking-tight">
            {t.bio}
          </h2>
          <p className="mt-3 max-w-prose whitespace-pre-line">{agent.bio}</p>
        </section>
      )}

      <section aria-labelledby="listings-title" className="mt-12 border-t border-line pt-10">
        <h2 id="listings-title" className="font-display text-2xl font-bold tracking-tight">
          {t.listings.replace('{name}', agent.name)}
        </h2>
        {listings.properties.length === 0 ? (
          <p className="mt-4 text-ink-muted">{t.noListings}</p>
        ) : (
          <ul className="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            {listings.properties.map((p) => (
              <li key={p.id} className="flex">
                <div className="flex w-full flex-col">
                  <PropertyCard property={p} locale={locale} messages={messages} money={settings} />
                </div>
              </li>
            ))}
          </ul>
        )}
      </section>
    </article>
  )
}
