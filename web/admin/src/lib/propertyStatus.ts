/** Mirrors the API's PropertyStatus workflow so the UI offers only legal moves. The API stays the authority (422 otherwise). */
export const PROPERTY_STATUSES = ['draft', 'available', 'under_offer', 'sold', 'rented', 'archived'] as const
export type PropertyStatusValue = (typeof PROPERTY_STATUSES)[number]

const NEXT: Record<PropertyStatusValue, PropertyStatusValue[]> = {
  draft: ['available', 'archived'],
  available: ['draft', 'under_offer', 'sold', 'rented', 'archived'],
  under_offer: ['available', 'sold', 'rented', 'archived'],
  sold: ['available', 'archived'],
  rented: ['available', 'archived'],
  archived: ['draft'],
}

export const statusLabel = (status: string) => status.replace(/_/g, ' ').replace(/^./, (c) => c.toUpperCase())

/** Statuses a property can move to: `sold` only for sale listings, `rented` only for rent listings. */
export function nextStatuses(status: string, listingType: string): PropertyStatusValue[] {
  const moves = NEXT[status as PropertyStatusValue] ?? []

  return moves.filter((to) => !(to === 'sold' && listingType !== 'sale') && !(to === 'rented' && listingType !== 'rent'))
}
