import { getPropertiesBySlugs, getSettings } from '../../../lib/api'
import { isLocale } from '../../../lib/i18n'

const SLUG = /^[a-z0-9]+(?:-[a-z0-9]+)*$/

/**
 * `GET /api/properties?locale=en&slugs=a,b,c`: the saved and compared lists live in the visitor's browser, so the
 * browser asks for the cards of those slugs here (the API itself is not reachable from the browser). Public data only;
 * slugs that are no longer public are simply missing from the answer.
 */
export async function GET(request: Request) {
  const url = new URL(request.url)
  const locale = url.searchParams.get('locale') ?? ''
  const slugs = (url.searchParams.get('slugs') ?? '').split(',').filter((s) => SLUG.test(s)).slice(0, 48)

  if (!isLocale(locale)) return Response.json({ error: 'unknown locale' }, { status: 400 })

  const [properties, settings] = await Promise.all([getPropertiesBySlugs(locale, slugs), getSettings(locale)])

  return Response.json({ properties, money: { currency: settings.currency ?? null, currency_symbol: settings.currency_symbol ?? null } })
}
