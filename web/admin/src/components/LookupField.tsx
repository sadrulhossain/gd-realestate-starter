import { useQuery } from '@tanstack/react-query'
import { useEffect, useId, useState } from 'react'
import { api } from '../api'

/**
 * Pick one record from a long list: type to search (`${endpoint}?q=`), choose a result, the form value is its id.
 * Replaces a select whose options would be cut off by pagination (properties, leads).
 */
export function LookupField({
  label,
  name,
  required,
  hint,
  lookup,
  value,
  initialLabel,
  error,
  onChange,
}: {
  label: string
  name: string
  required?: boolean
  hint?: string
  lookup: { endpoint: string; label: (item: never) => string }
  value: unknown
  initialLabel?: string
  error?: string
  onChange: (value: unknown) => void
}) {
  const id = useId()
  const [text, setText] = useState('')
  const [term, setTerm] = useState('')
  const [picked, setPicked] = useState<string | undefined>(initialLabel)

  // Search a moment after the last keystroke, not on every one.
  useEffect(() => {
    const timer = setTimeout(() => setTerm(text.trim()), 250)

    return () => clearTimeout(timer)
  }, [text])

  const results = useQuery({
    queryKey: ['lookup', lookup.endpoint, term],
    enabled: term.length >= 2,
    staleTime: 30_000,
    queryFn: async () => ((await api.request('GET', lookup.endpoint, { query: { q: term } })) as { data: { id: number }[] }).data,
  })

  const hasValue = value !== null && value !== undefined && value !== ''

  return (
    <div className="space-y-1">
      <label htmlFor={id} className="block text-sm text-ink-muted">
        {label}
        {required && <span aria-hidden="true"> *</span>}
      </label>
      {hasValue && (
        <p className="flex items-center gap-2 text-sm">
          <span data-testid={`${name}-picked`}>{picked ?? `#${String(value)}`}</span>
          <button
            type="button"
            className="text-primary-700 underline"
            onClick={() => {
              setPicked(undefined)
              onChange(null)
            }}
          >
            Clear
          </button>
        </p>
      )}
      <input id={id} name={name} type="search" className="field" autoComplete="off" placeholder="Type at least 2 characters to search" value={text} onChange={(e) => setText(e.target.value)} aria-invalid={error ? true : undefined} />
      {term.length >= 2 && (
        <ul aria-label={`${label} results`} className="max-h-40 overflow-auto rounded-md border border-line text-sm">
          {results.isPending && <li className="px-2 py-1 text-ink-muted">Searching...</li>}
          {results.data?.length === 0 && <li className="px-2 py-1 text-ink-muted">No results</li>}
          {results.data?.map((item) => (
            <li key={item.id}>
              <button
                type="button"
                className="block w-full px-2 py-1 text-start hover:bg-primary-50"
                onClick={() => {
                  setPicked(lookup.label(item as never))
                  onChange(item.id)
                  setText('')
                }}
              >
                {lookup.label(item as never)}
              </button>
            </li>
          ))}
        </ul>
      )}
      {hint && !error && <p className="text-sm text-ink-muted">{hint}</p>}
      {error && (
        <p role="alert" className="text-sm text-danger">
          {error}
        </p>
      )}
    </div>
  )
}
