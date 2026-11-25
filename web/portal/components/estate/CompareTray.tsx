'use client'

import Link from 'next/link'
import { clearSaved, useSaved } from '../../lib/saved'
import { localePath, type Messages } from '../../lib/i18n'

/** A bar pinned to the bottom of the screen while at least one property is picked for comparison. */
export function CompareTray({ locale, messages }: { locale: string; messages: Messages }) {
  const list = useSaved('compare')
  const t = messages.estate.saved

  if (list.length === 0) return null

  return (
    <div className="fixed inset-x-0 bottom-0 z-40 border-t border-line bg-night text-white shadow-lift">
      <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-3">
        <p className="font-semibold">{t.compareTray.replace('{count}', String(list.length))}</p>
        <div className="flex items-center gap-3">
          <button type="button" onClick={() => clearSaved('compare')} className="rounded-pill px-4 py-2 text-sm font-semibold text-white underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">
            {t.clear}
          </button>
          <Link href={localePath(locale, '/compare')} className="inline-flex rounded-pill bg-accent-400 px-5 py-2 text-sm font-semibold text-ink hover:bg-accent-300 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">
            {t.openCompare}
          </Link>
        </div>
      </div>
    </div>
  )
}
