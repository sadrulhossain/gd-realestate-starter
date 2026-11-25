'use client'

import { useState } from 'react'
import { MAX_COMPARE, toggleSaved, useSaved } from '../../lib/saved'
import type { Messages } from '../../lib/i18n'

const icon = 'size-5'

/** Heart toggle. aria-pressed carries the state, the label names the property, so screen readers hear "Save Lakeview flat, toggle button". */
export function FavouriteButton({ slug, title, messages, className = '' }: { slug: string; title: string; messages: Messages; className?: string }) {
  const saved = useSaved('favourites').includes(slug)
  const t = messages.estate.saved

  return (
    <button
      type="button"
      aria-pressed={saved}
      aria-label={(saved ? t.savedLabel : t.saveLabel).replace('{title}', title)}
      onClick={() => toggleSaved('favourites', slug)}
      className={`relative z-10 inline-flex size-10 items-center justify-center rounded-pill bg-white/95 text-brand-700 shadow-soft transition hover:bg-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 ${className}`}
    >
      <svg viewBox="0 0 24 24" aria-hidden="true" className={icon} fill={saved ? 'currentColor' : 'none'} stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1 1.1L12 21l7.8-7.5 1-1.1a5.5 5.5 0 0 0 0-7.8z" />
      </svg>
    </button>
  )
}

/** Compare toggle with a visible word. The fourth pick explains itself in a live region instead of failing silently. */
export function CompareButton({ slug, title, messages, className = '' }: { slug: string; title: string; messages: Messages; className?: string }) {
  const list = useSaved('compare')
  const on = list.includes(slug)
  const [full, setFull] = useState(false)
  const t = messages.estate.saved

  return (
    <span className={`relative z-10 inline-flex flex-col ${className}`}>
      <button
        type="button"
        aria-pressed={on}
        aria-label={(on ? t.comparingLabel : t.compareLabel).replace('{title}', title)}
        onClick={() => setFull(!toggleSaved('compare', slug))}
        className={`inline-flex items-center gap-1.5 rounded-pill px-3 py-1.5 text-sm font-semibold shadow-soft transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 ${on ? 'bg-brand-700 text-white' : 'bg-white/95 text-brand-700 hover:bg-white'}`}
      >
        {on ? t.comparing : t.compare}
      </button>
      <span role="status" className={full && !on && list.length >= MAX_COMPARE ? 'absolute top-full mt-1 w-48 rounded-md bg-night p-2 text-xs text-white shadow-lift' : 'sr-only'}>
        {full && !on && list.length >= MAX_COMPARE ? t.compareFull : ''}
      </span>
    </span>
  )
}
