import { formatPrice, type Money } from '../../lib/format'
import type { Messages } from '../../lib/i18n'

/** A price from integer minor units. Rent listings get their period ("/ month"); a zero price reads "Price on request". */
export function PriceTag({ minor, money, period, messages, className = '' }: { minor: number; money: Money; period?: string | null; messages: Messages; className?: string }) {
  if (!minor) return <p className={`font-display font-bold text-ink-muted ${className}`}>{messages.estate.card.priceOnRequest}</p>

  const suffix = period ? (messages.estate.period as Record<string, string>)[period] : null

  return (
    <p className={`font-display font-extrabold tracking-tight text-brand-800 ${className}`}>
      {formatPrice(minor, money)}
      {suffix && <span className="ms-1 text-sm font-medium text-ink-muted">{suffix}</span>}
    </p>
  )
}
