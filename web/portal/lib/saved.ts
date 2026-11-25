'use client'

import { useSyncExternalStore } from 'react'

/**
 * Favourites and the compare list: property slugs kept in this browser's localStorage (no account, no cookie, nothing sent
 * to the server). Every read and write is guarded, because storage can be blocked or full (private windows, quotas).
 */
export type ListName = 'favourites' | 'compare'

export const MAX_COMPARE = 3
const KEY: Record<ListName, string> = { favourites: 'gd:favourites', compare: 'gd:compare' }
const EMPTY: string[] = []
const listeners = new Set<() => void>()
const cache = new Map<ListName, { raw: string | null; value: string[] }>()

function read(name: ListName): string[] {
  let raw: string | null = null
  try {
    raw = window.localStorage.getItem(KEY[name])
  } catch {
    return EMPTY
  }

  const hit = cache.get(name)
  if (hit && hit.raw === raw) return hit.value

  let value = EMPTY
  try {
    const parsed: unknown = raw ? JSON.parse(raw) : []
    if (Array.isArray(parsed)) value = parsed.filter((s): s is string => typeof s === 'string' && /^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(s)).slice(0, 48)
  } catch {
    value = EMPTY
  }
  cache.set(name, { raw, value })

  return value
}

function write(name: ListName, slugs: string[]) {
  try {
    window.localStorage.setItem(KEY[name], JSON.stringify(slugs))
  } catch {
    // Storage unavailable: the in-memory cache below still updates this tab for the session.
    cache.set(name, { raw: null, value: slugs })
  }
  listeners.forEach((l) => l())
}

function subscribe(listener: () => void) {
  listeners.add(listener)
  const onStorage = (e: StorageEvent) => {
    if (e.key === null || Object.values(KEY).includes(e.key)) listener()
  }
  window.addEventListener('storage', onStorage)

  return () => {
    listeners.delete(listener)
    window.removeEventListener('storage', onStorage)
  }
}

/** The saved slugs of a list; empty on the server and during hydration, then the stored value. */
export function useSaved(name: ListName): string[] {
  return useSyncExternalStore(
    subscribe,
    () => read(name),
    () => EMPTY,
  )
}

/** Toggle a slug. Compare is capped at three; the return value says whether the change was made. */
export function toggleSaved(name: ListName, slug: string): boolean {
  const current = read(name)
  if (current.includes(slug)) {
    write(name, current.filter((s) => s !== slug))

    return true
  }
  if (name === 'compare' && current.length >= MAX_COMPARE) return false
  write(name, [slug, ...current].slice(0, 48))

  return true
}

export function removeSaved(name: ListName, slug: string) {
  write(name, read(name).filter((s) => s !== slug))
}

export function clearSaved(name: ListName) {
  write(name, [])
}
