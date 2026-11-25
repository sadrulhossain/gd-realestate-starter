import AxeBuilder from '@axe-core/playwright'
import { expect, test } from '@playwright/test'

// The estate theme at phone (390) and desktop (1280) widths: home search and sections, no horizontal scroll, axe clean.
const sizes = [
  { name: 'phone', width: 390, height: 844 },
  { name: 'desktop', width: 1280, height: 900 },
]

for (const size of sizes) {
  test.describe(`estate @ ${size.width}px`, () => {
    test.use({ viewport: { width: size.width, height: size.height } })

    test('home has the search bar and never scrolls sideways', async ({ page }) => {
      await page.goto('/')
      await expect(page.getByRole('search', { name: 'Property search' })).toBeVisible()
      await expect(page.getByRole('button', { name: 'Search' })).toBeVisible()

      const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)
      expect(overflow).toBeLessThanOrEqual(0)
    })

    test('home search is a plain GET to /properties', async ({ page }) => {
      await page.goto('/')
      const form = page.getByRole('search', { name: 'Property search' })
      await expect(form).toHaveAttribute('method', 'get')
      await expect(form).toHaveAttribute('action', /\/properties$/)
      await expect(form.getByRole('radio', { name: 'Buy' })).toBeChecked()
    })

    test('home passes axe', async ({ page }) => {
      await page.goto('/')
      await expect(page.locator('h1').first()).toBeVisible()
      const { violations } = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze()

      expect(violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).slice(0, 3).join(' | ')}`)).toEqual([])
    })

    // Development only: production answers 404, so the check is skipped there.
    test('styleguide shows every component and passes axe', async ({ page }) => {
      const response = await page.goto('/styleguide')
      test.skip(response?.status() === 404, 'styleguide is not served in production')

      for (const title of ['Colour', 'Buttons', 'StatusBadge', 'PropertyCard', 'SearchBar']) {
        await expect(page.getByRole('heading', { name: title })).toBeVisible()
      }

      const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)
      expect(overflow).toBeLessThanOrEqual(0)

      const { violations } = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze()
      expect(violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).slice(0, 3).join(' | ')}`)).toEqual([])
    })
  })
}
