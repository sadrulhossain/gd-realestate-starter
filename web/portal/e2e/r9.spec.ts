import AxeBuilder from '@axe-core/playwright'
import { expect, test, type Page } from '@playwright/test'

// Agents, locations, projects and the enquiry / viewing form at phone (390) and desktop (1280) widths. Pages that need
// data skip on an empty catalogue. The viewing test leaves one lead named "E2E Smoke" with a viewing request.
const sizes = [
  { width: 390, height: 844 },
  { width: 1280, height: 900 },
]

const axe = async (page: Page) => {
  const { violations } = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze()

  return violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).slice(0, 3).join(' | ')}`)
}

const noSidewaysScroll = async (page: Page) => expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(0)

/** The datetime-local value for tomorrow at 11:00 in the browser's own time zone. */
const tomorrow = () => {
  const d = new Date(Date.now() + 86_400_000)
  const pad = (n: number) => String(n).padStart(2, '0')

  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T11:00`
}

async function firstPropertyPage(page: Page) {
  await page.goto('/properties')
  const link = page.locator('article h3 a').first()
  test.skip((await link.count()) === 0, 'no listings')
  await page.goto((await link.getAttribute('href'))!)
}

for (const size of sizes) {
  test.describe(`agents, locations, projects, enquiry @ ${size.width}px`, () => {
    test.use({ viewport: size })

    for (const path of ['/agents', '/locations', '/projects']) {
      test(`${path} loads, passes axe, never scrolls sideways`, async ({ page }) => {
        const response = await page.goto(path)
        test.skip(response?.status() === 404, `${path} is switched off (its home section is hidden)`)

        await expect(page.getByRole('heading', { level: 1 })).toBeVisible()
        await noSidewaysScroll(page)
        expect(await axe(page)).toEqual([])
      })
    }

    test('an agent profile, a location page and a project page open from their lists', async ({ page }) => {
      for (const [list, hrefPart] of [['/agents', '/agents/'], ['/locations', '/locations/'], ['/projects', '/projects/']] as const) {
        const response = await page.goto(list)
        if (response?.status() === 404) continue

        const link = page.locator(`main a[href*="${hrefPart}"]`).first()
        if ((await link.count()) === 0) continue

        await page.goto((await link.getAttribute('href'))!)
        await expect(page.getByRole('heading', { level: 1 })).toBeVisible()
        await expect(page.locator('link[rel="canonical"]')).toHaveCount(1)
        await noSidewaysScroll(page)
        expect(await axe(page)).toEqual([])
      }
    })

    test('unknown agent, location and project are 404s', async ({ page }) => {
      for (const path of ['/agents/no-such-agent', '/locations/no-such/place', '/projects/no-such-project']) {
        expect((await page.goto(path))?.status()).toBe(404)
      }
    })

    test('enquiry form: honeypot hidden, captcha loads, a wrong answer is refused', async ({ page }) => {
      await firstPropertyPage(page)
      const form = page.getByRole('form', { name: /^Enquire about/ })
      await expect(form).toBeVisible()
      await expect(form.locator('input[name="captcha_answer"]')).toBeEnabled()
      // Off-screen rather than display:none (some bots skip those), hidden from assistive tech, not focusable.
      await expect(form.locator('[aria-hidden="true"] input[name="website"]')).toHaveCount(1)
      await expect(form.locator('input[name="website"]')).toHaveAttribute('tabindex', '-1')
      const box = await form.locator('input[name="website"]').boundingBox()
      expect(box === null || box.x < -1000).toBe(true)

      await form.getByLabel('Name').fill('E2E Smoke')
      await form.getByLabel('Email').fill('e2e-smoke@example.com')
      await form.locator('input[name="captcha_answer"]').fill('99999')
      await form.getByRole('button', { name: 'Send enquiry' }).click()

      await expect(page.getByRole('alert')).toBeVisible()
      await expect(form.locator('input[name="captcha_answer"]')).toHaveValue('')
    })

    test('enquiry form: needs an email or phone, and a time when a viewing is ticked', async ({ page }) => {
      await firstPropertyPage(page)
      const form = page.getByRole('form', { name: /^Enquire about/ })
      await expect(form.locator('input[name="captcha_answer"]')).toBeEnabled()

      await form.getByLabel('Name').fill('E2E Smoke')
      await form.locator('input[name="captcha_answer"]').fill('1')
      await form.getByRole('button', { name: 'Send enquiry' }).click()
      await expect(form.getByText('Enter an email or a phone number.')).toBeVisible()

      await form.getByLabel('Email').fill('e2e-smoke@example.com')
      await form.getByLabel('I would like a viewing').check()
      await expect(form.getByLabel('Preferred date and time')).toBeVisible()
      await form.getByRole('button', { name: 'Request viewing' }).click()
      await expect(form.getByText('Choose a date and time.')).toBeVisible()
    })

    test('viewing request submits', async ({ page }) => {
      await firstPropertyPage(page)
      const form = page.getByRole('form', { name: /^Enquire about/ })
      const answer = form.locator('input[name="captcha_answer"]')
      await expect(answer).toBeEnabled()
      const [a, b] = ((await form.locator('label[for$="-captcha"]').textContent()) ?? '').match(/\d+/g)!.map(Number)

      await form.getByLabel('Name').fill('E2E Smoke')
      await form.getByLabel('Email').fill('e2e-smoke@example.com')
      await form.getByLabel('Message').fill('Automated smoke test viewing request.')
      await form.getByLabel('I would like a viewing').check()
      await form.getByLabel('Preferred date and time').fill(tomorrow())
      await answer.fill(String(a + b))
      await form.getByRole('button', { name: 'Request viewing' }).click()

      await expect(page.getByRole('status').filter({ hasText: 'viewing request' })).toBeVisible()
    })

    test('property page with the enquiry form passes axe', async ({ page }) => {
      await firstPropertyPage(page)
      await noSidewaysScroll(page)
      expect(await axe(page)).toEqual([])
    })
  })
}
