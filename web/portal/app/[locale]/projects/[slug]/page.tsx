import type { Metadata } from 'next'
import Link from 'next/link'
import { notFound } from 'next/navigation'
import { EnquiryForm } from '../../../../components/estate/EnquiryForm'
import { PropertyCard } from '../../../../components/estate/PropertyCard'
import { StatusBadge } from '../../../../components/estate/StatusBadge'
import { JsonLd } from '../../../../components/JsonLd'
import { getProject, getSettings, searchProperties } from '../../../../lib/api'
import { formatArea, formatPrice } from '../../../../lib/format'
import { getMessages, localePath } from '../../../../lib/i18n'
import { requireSection } from '../../../../lib/offer'
import { pageMetadata, projectJsonLd } from '../../../../lib/seo'

type Props = { params: Promise<{ locale: string; slug: string }> }

async function load(locale: string, slug: string) {
  await requireSection(locale, 'projects')
  if (!/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(slug)) notFound()
  const project = await getProject(locale, slug)
  if (!project) notFound()

  return project
}

// Rendered on first request, then cached (ISR) until the `projects` or `properties` tag is revalidated.
export function generateStaticParams() {
  return []
}

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { locale, slug } = await params
  const [project, settings] = await Promise.all([load(locale, slug), getSettings(locale)])

  return pageMetadata({ locale, path: `/projects/${project.slug}`, siteName: settings.site_name, title: project.seo_title || project.name, description: project.seo_description || project.description })
}

export default async function ProjectPage({ params }: Props) {
  const { locale, slug } = await params
  const [project, settings] = await Promise.all([load(locale, slug), getSettings(locale)])
  const messages = getMessages(locale)
  const t = messages.estate.projects
  const listings = await searchProperties(locale, new URLSearchParams({ project: String(project.id), per_page: '6' }))
  const place = [project.address_line, project.location?.name].filter(Boolean).join(', ')
  const handover = project.handover_date ? new Intl.DateTimeFormat(locale, { dateStyle: 'long' }).format(new Date(project.handover_date)) : null
  const status = (messages.estate.project as Record<string, string>)[project.status] ?? project.status

  return (
    <article className="mx-auto max-w-6xl px-4 py-10 sm:py-14">
      <JsonLd data={projectJsonLd(project, settings, locale)} />
      <Link href={localePath(locale, '/projects')} className="text-brand-700 hover:underline">
        <span aria-hidden="true" className="inline-block rtl:rotate-180">←</span> {t.all}
      </Link>

      <header className="mt-4 space-y-3 border-t-4 border-t-accent-400 pt-6">
        <p className="text-sm font-semibold tracking-wide text-brand-700 uppercase">{status}</p>
        <h1 className="font-display text-3xl font-extrabold tracking-tight text-balance sm:text-4xl">{project.name}</h1>
        <p className="text-lg text-ink-muted">{[project.developer ? t.by.replace('{name}', project.developer.name) : null, place, handover ? t.handover.replace('{date}', handover) : null].filter(Boolean).join(' - ')}</p>
        {project.from_price ? (
          <p>
            <span className="text-ink-muted">{messages.estate.home.projectFrom} </span>
            <span className="font-display text-2xl font-extrabold text-brand-800">{formatPrice(project.from_price, settings)}</span>
          </p>
        ) : null}
      </header>

      <div className="mt-10 grid gap-10 lg:grid-cols-[1fr_22rem]">
        <div className="space-y-10">
          {project.description && (
            <section aria-labelledby="about-title">
              <h2 id="about-title" className="font-display text-2xl font-bold tracking-tight">
                {t.about}
              </h2>
              <p className="mt-3 max-w-prose whitespace-pre-line">{project.description}</p>
            </section>
          )}

          <section aria-labelledby="units-title">
            <h2 id="units-title" className="font-display text-2xl font-bold tracking-tight">
              {t.units}
            </h2>
            {project.units.length === 0 ? (
              <p className="mt-3 text-ink-muted">{t.noUnits}</p>
            ) : (
              <div role="region" aria-label={t.units} tabIndex={0} className="mt-4 overflow-x-auto rounded-card border border-line bg-surface shadow-card focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600">
                <table className="w-full min-w-[34rem] border-collapse text-start">
                  <caption className="sr-only">{t.units}</caption>
                  <thead>
                    <tr className="border-b border-line text-sm text-ink-muted">
                      <th scope="col" className="p-3 text-start font-medium">{t.unitCode}</th>
                      <th scope="col" className="p-3 text-start font-medium">{messages.estate.saved.row.type}</th>
                      <th scope="col" className="p-3 text-start font-medium">{messages.estate.saved.row.beds}</th>
                      <th scope="col" className="p-3 text-start font-medium">{messages.estate.saved.row.area}</th>
                      <th scope="col" className="p-3 text-start font-medium">{t.price}</th>
                      <th scope="col" className="p-3 text-start font-medium">{messages.estate.saved.row.status}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {project.units.map((u) => (
                      <tr key={u.id} className="odd:bg-canvas">
                        <th scope="row" className="p-3 text-start font-medium">{u.code ?? u.name ?? '-'}</th>
                        <td className="p-3">{u.property_type?.name ?? '-'}</td>
                        <td className="p-3">{u.beds ?? '-'}</td>
                        <td className="p-3">{formatArea(u.area, u.area_unit, messages.estate.units) ?? '-'}</td>
                        <td className="p-3 font-medium">{u.price ? formatPrice(u.price, settings) : messages.estate.card.priceOnRequest}</td>
                        <td className="p-3">
                          <StatusBadge status={u.status} messages={messages} />
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </section>

          {listings.properties.length > 0 && (
            <section aria-labelledby="listings-title">
              <h2 id="listings-title" className="font-display text-2xl font-bold tracking-tight">
                {t.propertiesIn}
              </h2>
              <ul className="mt-4 grid gap-6 sm:grid-cols-2">
                {listings.properties.map((p) => (
                  <li key={p.id} className="flex">
                    <div className="flex w-full flex-col">
                      <PropertyCard property={p} locale={locale} messages={messages} money={settings} />
                    </div>
                  </li>
                ))}
              </ul>
            </section>
          )}
        </div>

        <aside aria-labelledby="enquiry-title" className="lg:sticky lg:top-24 lg:self-start">
          <section className="rounded-card border border-line bg-surface p-6 shadow-card">
            <h2 id="enquiry-title" className="font-display text-xl font-bold tracking-tight">
              {messages.estate.enquiry.heading}
            </h2>
            <p className="mt-1 mb-4 text-sm text-ink-muted">{messages.estate.enquiry.intro}</p>
            <EnquiryForm messages={messages} projectId={project.id} subject={project.name} />
          </section>
        </aside>
      </div>
    </article>
  )
}
