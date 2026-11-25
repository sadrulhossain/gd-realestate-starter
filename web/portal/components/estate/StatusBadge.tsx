import type { Messages } from '../../lib/i18n'

const tone: Record<string, string> = {
  available: 'bg-status-available text-status-available-ink',
  under_offer: 'bg-status-offer text-status-offer-ink',
  sold: 'bg-status-sold text-status-sold-ink',
  rented: 'bg-status-rented text-status-rented-ink',
}

/** A listing's status as a chip. The word is always shown; colour only reinforces it. Unknown statuses render neutral. */
export function StatusBadge({ status, messages }: { status: string; messages: Messages }) {
  const label = (messages.estate.status as Record<string, string>)[status] ?? status

  return <span className={`inline-block rounded-pill px-3 py-1 text-xs font-semibold ${tone[status] ?? 'bg-brand-50 text-brand-800'}`}>{label}</span>
}
