import type { Metadata } from 'next'
import { AgentCard } from '../../../components/estate/AgentCard'
import { getAgents, getSettings } from '../../../lib/api'
import { getMessages } from '../../../lib/i18n'
import { requireSection } from '../../../lib/offer'
import { pageMetadata } from '../../../lib/seo'

type Props = { params: Promise<{ locale: string }> }

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { locale } = await params
  const [section, settings] = await Promise.all([requireSection(locale, 'agents'), getSettings(locale)])

  return pageMetadata({ locale, path: '/agents', siteName: settings.site_name, title: section.title, description: section.content ?? getMessages(locale).estate.agents.intro })
}

export default async function Agents({ params }: Props) {
  const { locale } = await params
  const section = await requireSection(locale, 'agents')
  const messages = getMessages(locale)
  const t = messages.estate.agents
  const agents = await getAgents(locale)

  return (
    <div className="mx-auto max-w-6xl px-4 py-10 sm:py-14">
      <h1 className="font-display text-3xl font-extrabold tracking-tight text-balance sm:text-4xl">{section.title}</h1>
      <p className="mt-2 max-w-2xl text-ink-muted">{section.content || t.intro}</p>

      {agents.length === 0 ? (
        <p className="mt-10 text-ink-muted">{t.empty}</p>
      ) : (
        <ul className="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
          {agents.map((a) => (
            <li key={a.id} className="flex">
              <div className="flex w-full flex-col">
                <AgentCard agent={a} messages={messages} locale={locale} />
              </div>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
