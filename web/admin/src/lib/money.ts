/**
 * Money helpers. The API stores prices as integers in minor units (cents, paisa); screens show and edit major units.
 * `digits` is the currency's fraction digits (2 for most, 0 for JPY).
 */

/** Fraction digits of a currency code; 2 when the code is missing or unknown. */
export function fractionDigits(currency?: string | null): number {
  if (!currency) return 2

  try {
    return new Intl.NumberFormat('en', { style: 'currency', currency }).resolvedOptions().maximumFractionDigits ?? 2
  } catch {
    return 2
  }
}

/** Minor units -> major units (150000 -> 1500). */
export function toMajor(minor: number, digits = 2): number {
  return minor / 10 ** digits
}

/** Major units -> whole minor units, rounded (12.345 -> 1235). Empty or non-numeric input is null. */
export function toMinor(major: string | number | null | undefined, digits = 2): number | null {
  if (major === null || major === undefined || major === '') return null

  const n = typeof major === 'number' ? major : Number(major)

  return Number.isFinite(n) ? Math.round(n * 10 ** digits) : null
}

/** "BDT 1,500.00" style text for a list cell; falls back to "symbol amount" for a code Intl does not know. */
export function formatMoney(minor: unknown, currency?: string | null, symbol?: string | null): string {
  if (minor === null || minor === undefined || minor === '') return ''

  const digits = fractionDigits(currency)
  const major = toMajor(Number(minor), digits)
  const plain = major.toLocaleString('en', { minimumFractionDigits: digits, maximumFractionDigits: digits })

  return `${symbol || currency || ''} ${plain}`.trim()
}
