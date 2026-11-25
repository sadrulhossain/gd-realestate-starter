import type { ReactElement } from 'react'
import { formatArea } from '../../lib/format'
import type { Messages } from '../../lib/i18n'

const icon = 'size-4 shrink-0 text-brand-600'

function Bed() {
  return (
    <svg viewBox="0 0 24 24" aria-hidden="true" className={icon} fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <path d="M3 18v-7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v7M3 15h18M7 9V6h10v3" />
    </svg>
  )
}

function Bath() {
  return (
    <svg viewBox="0 0 24 24" aria-hidden="true" className={icon} fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <path d="M4 12h16v3a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4zM6 12V6a2 2 0 0 1 4 0M7 19l-1 2M17 19l1 2" />
    </svg>
  )
}

function Area() {
  return (
    <svg viewBox="0 0 24 24" aria-hidden="true" className={icon} fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5" />
    </svg>
  )
}

/**
 * Bedrooms, bathrooms and area as a description list ("3 Beds"). Missing facts are skipped; with none the list is not
 * rendered. Each icon is decorative: the visible label carries the meaning.
 */
export function FactList({ beds, baths, area, areaUnit, messages, className = '' }: { beds?: number | null; baths?: number | null; area?: string | number | null; areaUnit?: string | null; messages: Messages; className?: string }) {
  const f = messages.estate.facts
  const size = formatArea(area, areaUnit, messages.estate.units)
  const items = [
    beds != null && { key: 'beds', icon: <Bed />, label: f.beds, value: String(beds) },
    baths != null && { key: 'baths', icon: <Bath />, label: f.baths, value: String(baths) },
    size && { key: 'area', icon: <Area />, label: f.area, value: size },
  ].filter((i): i is { key: string; icon: ReactElement; label: string; value: string } => Boolean(i))

  if (items.length === 0) return null

  return (
    <dl className={`flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-ink-muted ${className}`}>
      {items.map((i) => (
        <div key={i.key} className="flex items-center gap-1.5">
          {i.icon}
          <dt className="sr-only">{i.label}</dt>
          <dd className="font-medium text-ink">{i.key === 'area' ? i.value : `${i.value} ${i.label}`}</dd>
        </div>
      ))}
    </dl>
  )
}
