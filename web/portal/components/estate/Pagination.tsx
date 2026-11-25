import Link from 'next/link'
import type { Messages } from '../../lib/i18n'

/** Previous / next with "Page 2 of 5". `hrefFor` builds the link so the filters in the URL are kept. */
export function Pagination({ page, lastPage, hrefFor, messages }: { page: number; lastPage: number; hrefFor: (page: number) => string; messages: Messages }) {
  const t = messages.estate.list
  if (lastPage <= 1) return null

  const link = 'rounded-pill border border-line px-5 py-2.5 font-medium hover:bg-brand-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600'

  return (
    <nav aria-label={t.pagination} className="mt-10 flex items-center justify-between gap-4">
      {page > 1 ? (
        <Link href={hrefFor(page - 1)} rel="prev" className={link}>
          <span aria-hidden="true" className="inline-block rtl:rotate-180">←</span> {t.previous}
        </Link>
      ) : (
        <span />
      )}
      <span className="text-ink-muted">{t.pageOf.replace('{page}', String(page)).replace('{last}', String(lastPage))}</span>
      {page < lastPage ? (
        <Link href={hrefFor(page + 1)} rel="next" className={link}>
          {t.next} <span aria-hidden="true" className="inline-block rtl:rotate-180">→</span>
        </Link>
      ) : (
        <span />
      )}
    </nav>
  )
}
