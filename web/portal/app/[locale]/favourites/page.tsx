import type { Metadata } from 'next'
import { notFound } from 'next/navigation'
import { FavouritesList } from '../../../components/estate/SavedProperties'
import { getSettings } from '../../../lib/api'
import { getMessages, isLocale } from '../../../lib/i18n'
import { pageMetadata } from '../../../lib/seo'

type Props = { params: Promise<{ locale: string }> }

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { locale } = await params
  if (!isLocale(locale)) return {}
  const settings = await getSettings(locale)
  const t = getMessages(locale).estate.saved

  // Personal to the visitor's browser: nothing here for a search engine.
  return { ...pageMetadata({ locale, path: '/favourites', siteName: settings.site_name, title: t.favouritesTitle, description: t.favouritesIntro }), robots: { index: false, follow: false } }
}

export default async function Favourites({ params }: Props) {
  const { locale } = await params
  if (!isLocale(locale)) notFound()
  const messages = getMessages(locale)
  const t = messages.estate.saved

  return (
    <div className="mx-auto max-w-6xl px-4 py-10 sm:py-14">
      <h1 className="font-display text-3xl font-extrabold tracking-tight text-balance sm:text-4xl">{t.favouritesTitle}</h1>
      <p className="mt-2 mb-8 text-ink-muted">{t.favouritesIntro}</p>
      <FavouritesList locale={locale} messages={messages} />
    </div>
  )
}
