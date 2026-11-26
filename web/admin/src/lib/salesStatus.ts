/** Mirrors of the API's LeadStatus and ViewingStatus tables, so menus offer only legal moves. The API stays the authority. */
export const LEAD_STATUSES = ['new', 'contacted', 'qualified', 'viewing', 'negotiation', 'won', 'lost'] as const
export const VIEWING_STATUSES = ['scheduled', 'confirmed', 'completed', 'cancelled', 'no_show'] as const

const LEAD_NEXT: Record<string, string[]> = {
  new: ['contacted', 'lost'],
  contacted: ['qualified', 'lost'],
  qualified: ['viewing', 'negotiation', 'lost'],
  viewing: ['qualified', 'negotiation', 'lost'],
  negotiation: ['viewing', 'won', 'lost'],
  won: [],
  lost: ['new'],
}

const VIEWING_NEXT: Record<string, string[]> = {
  scheduled: ['confirmed', 'cancelled', 'no_show'],
  confirmed: ['completed', 'cancelled', 'no_show'],
  completed: [],
  cancelled: [],
  no_show: [],
}

export const nextLeadStatuses = (status: string): string[] => LEAD_NEXT[status] ?? []
export const nextViewingStatuses = (status: string): string[] => VIEWING_NEXT[status] ?? []

/** Tailwind classes per status (full literal strings, Tailwind only sees those). */
const TONE: Record<string, string> = {
  new: 'bg-primary-100 text-primary-800',
  contacted: 'bg-surface-muted text-ink',
  qualified: 'bg-surface-muted text-ink',
  viewing: 'bg-surface-muted text-ink',
  negotiation: 'bg-surface-muted text-ink',
  won: 'bg-success/15 text-success',
  lost: 'bg-danger/10 text-danger',
  scheduled: 'bg-primary-100 text-primary-800',
  confirmed: 'bg-primary-100 text-primary-800',
  completed: 'bg-success/15 text-success',
  cancelled: 'bg-surface-muted text-ink-muted',
  no_show: 'bg-danger/10 text-danger',
  // Rentals
  draft: 'bg-surface-muted text-ink',
  active: 'bg-success/15 text-success',
  ended: 'bg-surface-muted text-ink-muted',
  terminated: 'bg-surface-muted text-ink-muted',
  pending: 'bg-primary-100 text-primary-800',
  partial: 'bg-surface-muted text-ink',
  paid: 'bg-success/15 text-success',
  overdue: 'bg-danger/10 text-danger',
  void: 'bg-surface-muted text-ink-muted',
  open: 'bg-primary-100 text-primary-800',
  in_progress: 'bg-surface-muted text-ink',
  resolved: 'bg-success/15 text-success',
}

export const statusTone = (status: string): string => TONE[status] ?? 'bg-surface-muted text-ink'
