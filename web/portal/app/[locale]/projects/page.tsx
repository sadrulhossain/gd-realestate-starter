import type { Metadata } from 'next'
import { Pagination } from '../../../components/estate/Pagination'
import { ProjectCard } from '../../../components/estate/ProjectCard'
import { getProjectPage, getSettings } from '../../../lib/api'
import { getMessages, localePath } from '../../../lib/i18n'
import { requireSection } from '../../../lib/offer'
import { pageMetadata } from '../../../lib/seo'

type Props = { params: Promise<{ locale: string }>; searchParams: Promise<{ page?: string }> }

const pageOf = (raw?: string) => (raw && /^\d{1,4}$/.test(raw) && Number(raw) > 0 ? Number(raw) : 1)

export async function generateMetadata({ params, searchParams }: Props): Promise<Metadata> {
  const { locale } = await params
  const page = pageOf((await searchParams).page)
  const [section, settings] = await Promise.all([requireSection(locale, 'projects'), getSettings(locale)])
  const meta = pageMetadata({ locale, path: '/projects', siteName: settings.site_name, title: `${section.title}${page > 1 ? ` (${page})` : ''}`, description: section.content ?? getMessages(locale).estate.projects.intro })

  return page > 1 ? { ...meta, robots: { index: false, follow: true } } : meta
}

export default async function Projects({ params, searchParams }: Props) {
  const { locale } = await params
  const section = await requireSection(locale, 'projects')
  const messages = getMessages(locale)
  const t = messages.estate.projects
  const [settings, result] = await Promise.all([getSettings(locale), getProjectPage(locale, pageOf((await searchParams).page))])
  const hrefFor = (page: number) => `${localePath(locale, '/projects')}${page > 1 ? `?page=${page}` : ''}`

  return (
    <div className="mx-auto max-w-6xl px-4 py-10 sm:py-14">
      <h1 className="font-display text-3xl font-extrabold tracking-tight text-balance sm:text-4xl">{section.title}</h1>
      <p className="mt-2 max-w-2xl text-ink-muted">{section.content || t.intro}</p>

      {result.projects.length === 0 ? (
        <p className="mt-10 text-ink-muted">{t.empty}</p>
      ) : (
        <ul className="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          {result.projects.map((p) => (
            <li key={p.id} className="flex">
              <div className="flex w-full flex-col">
                <ProjectCard project={p} locale={locale} messages={messages} money={settings} />
              </div>
            </li>
          ))}
        </ul>
      )}
      <Pagination page={result.page} lastPage={result.lastPage} hrefFor={hrefFor} messages={messages} />
    </div>
  )
}
