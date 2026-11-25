/** Price and size formatting for listings. The API holds prices as integer minor units (cents, paisa). */

export interface Money {
  currency?: string | null
  currency_symbol?: string | null
}

/** Fraction digits of a currency code; 2 when unknown. */
function digits(currency?: string | null): number {
  if (!currency) return 2

  try {
    return new Intl.NumberFormat('en', { style: 'currency', currency }).resolvedOptions().maximumFractionDigits ?? 2
  } catch {
    return 2
  }
}

/** "BDT 1,500,000" from minor units; whole amounts drop the zero decimals ("BDT 1,500,000.50" keeps them). */
export function formatPrice(minor: number, money: Money): string {
  const d = digits(money.currency)
  const major = minor / 10 ** d
  const whole = Number.isInteger(major)
  const text = major.toLocaleString('en', { minimumFractionDigits: whole ? 0 : d, maximumFractionDigits: d })

  return `${money.currency_symbol || money.currency || ''} ${text}`.trim()
}

/** "1,200 sq ft"; null when there is no area. `labels` are the unit names from the messages. */
export function formatArea(area: string | number | null | undefined, unit: string | null | undefined, labels: Record<string, string>): string | null {
  if (area === null || area === undefined || area === '') return null

  const n = Number(area)
  const value = Number.isFinite(n) ? n.toLocaleString('en', { maximumFractionDigits: 1 }) : String(area)

  return `${value} ${labels[unit ?? ''] ?? unit ?? ''}`.trim()
}

/** Decimal places of a currency code, exported for scaling typed prices to minor units. */
export const currencyDigits = digits
