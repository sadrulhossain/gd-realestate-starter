export interface LocationRow {
  id: number
  parent_id: number | null
  name: string
}

/** id -> "Country > City > Area" for a flat location list. A broken parent link stops the walk (no cycles). */
export function locationPaths(items: LocationRow[]): Map<number, string> {
  const byId = new Map(items.map((l) => [l.id, l]))
  const paths = new Map<number, string>()

  for (const item of items) {
    const names: string[] = []
    const seen = new Set<number>()

    for (let node: LocationRow | undefined = item; node && !seen.has(node.id); node = node.parent_id ? byId.get(node.parent_id) : undefined) {
      seen.add(node.id)
      names.unshift(node.name)
    }

    paths.set(item.id, names.join(' > '))
  }

  return paths
}

/** Select options for a location field, sorted by path so children follow their parent. */
export function locationOptions(items: LocationRow[]): { value: number; label: string }[] {
  return [...locationPaths(items)].map(([value, label]) => ({ value, label })).sort((a, b) => a.label.localeCompare(b.label))
}
