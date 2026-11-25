import AxeBuilder from '@axe-core/playwright'
import { expect, test, type Page } from '@playwright/test'

// Search, detail, favourites and compare at phone (390) and desktop (1280) widths. Checks that need a listing skip when
// the catalogue is empty, so the spec passes on a bare install and does more on a seeded one.
const sizes = [
  { width: 390, height: 844 },
  { width: 1280, height: 900 },
]

const axe = async (page: Page) => {
  const { violations } = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze()

  return violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).slice(0, 3).join(' | ')}`)
}

const noSidewaysScroll = async (page: Page) => expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(0)

for (const size of sizes) {
  test.describe(`properties @ ${size.width}px`, () => {
    test.use({ viewport: size })

    test('search page: filters live in the URL', async ({ page }) => {
      await page.goto('/properties')
      await expect(page.getByRole('heading', { level: 1, name: 'Properties' })).toBeVisible()
      await noSidewaysScroll(page)

      const form = page.getByRole('search', { name: 'Property search' })
      await form.getByText('Rent', { exact: true }).click()
      await form.getByLabel('Bedrooms').selectOption('2')
      await form.getByRole('button', { name: 'Show results' }).click()

      await expect(page).toHaveURL(/listing_type=rent/)
      await expect(page).toHaveURL(/beds_min=2/)
      await expect(form.getByLabel('Bedrooms')).toHaveValue('2')
      await expect(form.getByRole('radio', { name: 'Rent' })).toBeChecked()
    })

    test('junk in the query is ignored, not an error', async ({ page }) => {
      const response = await page.goto('/properties?beds_min=abc&price_min=-5&sort=zzz&page=0&listing_type=x')
      expect(response?.status()).toBe(200)
      await expect(page.getByRole('heading', { level: 1, name: 'Properties' })).toBeVisible()
    })

    test('unknown property is a 404', async ({ page }) => {
      const response = await page.goto('/properties/no-such-property')
      expect(response?.status()).toBe(404)
    })

    for (const path of ['/properties', '/favourites', '/compare']) {
      test(`axe: ${path}`, async ({ page }) => {
        await page.goto(path)
        await expect(page.locator('h1').first()).toBeVisible()
        await noSidewaysScroll(page)
        expect(await axe(page)).toEqual([])
      })
    }

    test('favourite and compare survive a reload and show on their pages', async ({ page }) => {
      await page.goto('/properties')
      const cards = page.locator('article')
      test.skip((await cards.count()) === 0, 'no listings to save')

      const title = (await cards.first().getByRole('heading', { level: 3 }).innerText()).trim()
      const heart = cards.first().getByRole('button', { name: new RegExp(`^Save ${title}`) })
      await heart.click()
      await expect(cards.first().getByRole('button', { name: new RegExp(`^Remove ${title} from favourites`) })).toHaveAttribute('aria-pressed', 'true')
      await cards.first().getByRole('button', { name: new RegExp(`^Add ${title} to compare`) }).click()
      await expect(page.getByText('Compare (1)')).toBeVisible()

      await page.reload()
      await expect(cards.first().getByRole('button', { name: new RegExp(`^Remove ${title} from favourites`) })).toBeVisible()

      await page.goto('/favourites')
      await expect(page.getByRole('heading', { level: 3, name: title })).toBeVisible()
      expect(await axe(page)).toEqual([])

      await page.goto('/compare')
      await expect(page.getByRole('table')).toBeVisible()
      await expect(page.getByRole('columnheader', { name: new RegExp(title) })).toBeVisible()
      await noSidewaysScroll(page)
      expect(await axe(page)).toEqual([])
    })

    test('detail page: facts, structured data, no sideways scroll', async ({ page }) => {
      await page.goto('/properties')
      const link = page.locator('article h3 a').first()
      test.skip((await link.count()) === 0, 'no listings')

      // A hard load, like a crawler: soft navigation can briefly keep the previous page's head tags.
      await page.goto((await link.getAttribute('href'))!)
      await expect(page.getByRole('heading', { level: 1 })).toBeVisible()
      const ld = await page.locator('script[type="application/ld+json"]').evaluateAll((els) => els.map((e) => e.textContent ?? ''))
      expect(ld.some((t) => t.includes('RealEstateListing'))).toBe(true)
      await expect(page.locator('link[rel="canonical"]')).toHaveCount(1)
      await noSidewaysScroll(page)
      expect(await axe(page)).toEqual([])
    })
  })
}
