import Link from 'next/link'

/**
 * A browse tile: one big link with a name and an optional hint ("Apartments", "Dhaka"). Used for property types and
 * popular locations. The initial letter is a decorative mark, the name carries the meaning.
 */
export function Tile({ href, name, hint }: { href: string; name: string; hint?: string }) {
  return (
    <Link href={href} className="group flex items-center gap-4 rounded-card border border-line bg-surface p-4 shadow-card transition duration-300 hover:-translate-y-1 hover:border-brand-300 hover:shadow-card-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600">
      <span aria-hidden="true" className="flex size-12 shrink-0 items-center justify-center rounded-md bg-brand-700 font-display text-xl font-bold text-white transition group-hover:bg-accent-400 group-hover:text-ink">
        {name.trim().charAt(0).toUpperCase()}
      </span>
      <span className="min-w-0">
        <span className="block truncate font-display text-lg font-bold tracking-tight">{name}</span>
        {hint && <span className="block truncate text-sm text-ink-muted">{hint}</span>}
      </span>
    </Link>
  )
}
