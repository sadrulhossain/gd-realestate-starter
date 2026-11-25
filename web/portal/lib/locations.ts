import type { Location } from './api'

/**
 * Location landing pages live at their full path (`/locations/bangladesh/dhaka/gulshan`): a slug is only unique within
 * its parent, so the chain is what makes the URL unambiguous. The API gives a flat list; these helpers walk it.
 */

/** The location with its ancestors, root first. A broken or looping parent link ends the chain. */
export function chainOf(all: Location[], location: Location): Location[] {
  const byId = new Map(all.map((l) => [l.id, l]))
  const chain: Location[] = []
  const seen = new Set<number>()

  for (let node: Location | undefined = location; node && !seen.has(node.id); node = node.parent_id ? byId.get(node.parent_id) : undefined) {
    seen.add(node.id)
    chain.unshift(node)
  }

  return chain
}

/** Path below `/locations` for a location: its slugs from the root down. */
export const locationSlugPath = (all: Location[], location: Location): string => chainOf(all, location).map((l) => l.slug).join('/')

/** The location a slug path points to, or undefined when any step does not exist. */
export function resolveLocation(all: Location[], slugs: string[]): Location | undefined {
  let parent: number | null = null
  let found: Location | undefined

  for (const slug of slugs) {
    found = all.find((l) => l.slug === slug && (l.parent_id ?? null) === parent)
    if (!found) return undefined
    parent = found.id
  }

  return found
}

export const childrenOf = (all: Location[], location: Location): Location[] => all.filter((l) => l.parent_id === location.id)
