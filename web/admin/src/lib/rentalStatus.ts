/** Mirrors of the API's LeaseStatus and MaintenanceStatus tables, so menus offer only legal moves. The API stays the authority. */
export const LEASE_STATUSES = ['draft', 'active', 'ended', 'terminated', 'cancelled'] as const
export const CHARGE_STATUSES = ['overdue', 'pending', 'partial', 'paid', 'void'] as const
export const MAINTENANCE_STATUSES = ['open', 'in_progress', 'resolved', 'cancelled'] as const
export const PRIORITIES = ['low', 'normal', 'high', 'urgent'] as const
export const PAYMENT_METHODS = ['cash', 'bank', 'cheque', 'online', 'other'] as const

const LEASE_NEXT: Record<string, string[]> = {
  draft: ['active', 'cancelled'],
  active: ['ended', 'terminated'],
  ended: [],
  terminated: [],
  cancelled: [],
}

const MAINTENANCE_NEXT: Record<string, string[]> = {
  open: ['in_progress', 'resolved', 'cancelled'],
  in_progress: ['open', 'resolved', 'cancelled'],
  resolved: ['open'],
  cancelled: ['open'],
}

export const nextLeaseStatuses = (status: string): string[] => LEASE_NEXT[status] ?? []
export const nextMaintenanceStatuses = (status: string): string[] => MAINTENANCE_NEXT[status] ?? []
