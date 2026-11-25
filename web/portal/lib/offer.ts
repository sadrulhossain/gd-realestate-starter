import { notFound } from 'next/navigation'
import { getSections, type Section } from './api'
import { isLocale } from './i18n'

/**
 * The section that switches a page on: when an admin hides it, the page is not offered and answers 404 (the same rule
 * as the other section pages). Its title and text become the page's heading and intro.
 */
export async function requireSection(locale: string, key: string): Promise<Section> {
  if (!isLocale(locale)) notFound()
  const section = (await getSections(locale)).find((s) => s.key === key)
  if (!section) notFound()

  return section
}
