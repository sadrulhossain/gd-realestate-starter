import { statusLabel } from '../lib/propertyStatus'
import { statusTone } from '../lib/salesStatus'

/** A status as a small coloured pill; the text is always shown, colour only reinforces it. */
export function StatusBadge({ status }: { status: unknown }) {
  const value = String(status ?? '')

  return <span className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${statusTone(value)}`}>{statusLabel(value)}</span>
}
