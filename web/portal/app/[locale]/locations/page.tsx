import type { Metadata } from 'next'
import Link from 'next/link'
import { Tile } from '../../../components/estate/Tile'
import { getLocations, getSettings } from '../../../lib/api'
import { getMessages, localePath } from '../../../lib/i18n'
import { childrenOf, locationSlugPath } from '../../../lib/locations'
import { requireSection } from '../../../lib/offer'
import { pageMetadata } from '../../../lib/seo'

type Props = { params: Promise<{ locale: string }> }

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { locale } = await params
  const [section, settings] = await Promise.all([requireSection(locale, 'locations'), getSettings(locale)])

  return pageMetadata({ locale, path: '/locations', siteName: settings.site_name, title: section.title, description: section.content ?? getMessages(locale).estate.locations.intro })
}

export default async function Locations({ params }: Props) {
  const { locale } = await params
  const section = await requireSection(locale, 'locations')
  const t = getMessages(locale).estate.locations
  const all = await getLocations(locale)
  const roots = all.filter((l) => l.parent_id === null)
  const href = (slugPath: string) => localePath(locale, `/locations/${slugPath}`)

  return (
    <div className="mx-auto max-w-6xl px-4 py-10 sm:py-14">
      <h1 className="font-display text-3xl font-extrabold tracking-tight text-balance sm:text-4xl">{section.title}</h1>
      <p className="mt-2 max-w-2xl text-ink-muted">{section.content || t.intro}</p>

      {all.length === 0 ? (
        <p className="mt-10 text-ink-muted">{t.empty}</p>
      ) : (
        <div className="mt-10 space-y-10">
          {roots.map((root) => {
            const children = childrenOf(all, root)

            return (
              <section key={root.id} aria-labelledby={`loc-${root.id}`}>
                <h2 id={`loc-${root.id}`} className="font-display text-2xl font-bold tracking-tight">
                  <Link href={href(locationSlugPath(all, root))} className="hover:text-brand-700 hover:underline">
                    {root.name}
                  </Link>
                </h2>
                {children.length > 0 && (
                  <ul className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {children.map((child) => (
                      <li key={child.id}>
                        <Tile href={href(locationSlugPath(all, child))} name={child.name} hint={t.inLocation.replace('{name}', child.name)} />
                      </li>
                    ))}
                  </ul>
                )}
              </section>
            )
          })}
        </div>
      )}
    </div>
  )
}
