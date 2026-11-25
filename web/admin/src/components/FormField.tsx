import { useId, useState } from 'react'
import { Icon, ICON_OPTIONS } from './Icon'
import { useCurrency } from './Money'
import { toMajor, toMinor } from '../lib/money'
import { RichText } from './RichText'

export type FieldType = 'text' | 'textarea' | 'number' | 'money' | 'checkbox' | 'select' | 'password' | 'checkgroup' | 'richtext' | 'date' | 'datetime' | 'icon'

export interface FieldOption {
  value: string | number
  label: string
  /** checkgroup only: options sharing a group form one row of a matrix, the label being the column. */
  group?: string
}

export interface FormFieldProps {
  label: string
  name: string
  type?: FieldType
  value: unknown
  onChange: (value: unknown) => void
  error?: string
  required?: boolean
  hint?: string
  options?: FieldOption[]
}

const control = 'field'

const pad = (n: number) => String(n).padStart(2, '0')

/** API ISO timestamp -> the local "YYYY-MM-DDTHH:mm" a datetime-local input shows. */
export function toLocalInput(value: unknown): string {
  if (!value) return ''
  const d = new Date(String(value))
  if (Number.isNaN(d.getTime())) return ''

  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

/** The local input value -> an ISO instant for the API ('' stays empty). */
export function fromLocalInput(value: string): string {
  if (!value) return ''
  const d = new Date(value)

  return Number.isNaN(d.getTime()) ? '' : d.toISOString()
}

/** One labelled input with its validation error. The label is associated for screen readers and tests. */
export function FormField({ label, name, type = 'text', value, onChange, error, required, hint, options }: FormFieldProps) {
  const id = useId()
  const describedBy = error ? `${id}-error` : hint ? `${id}-hint` : undefined
  const common = { id, name, required, 'aria-invalid': error ? true : undefined, 'aria-describedby': describedBy }

  let input
  if (type === 'textarea') {
    input = <textarea {...common} rows={4} className={control} value={String(value ?? '')} onChange={(e) => onChange(e.target.value)} />
  } else if (type === 'richtext') {
    input = <RichText id={id} label={label} value={String(value ?? '')} onChange={onChange} />
  } else if (type === 'select') {
    input = (
      <select {...common} className={control} value={String(value ?? '')} onChange={(e) => onChange(e.target.value)}>
        <option value="">-</option>
        {options?.map((o) => (
          <option key={o.value} value={o.value}>
            {o.label}
          </option>
        ))}
      </select>
    )
  } else if (type === 'checkgroup') {
    input = <CheckGroup id={id} name={name} label={label} value={Array.isArray(value) ? (value as string[]) : []} options={options ?? []} onChange={onChange} />
  } else if (type === 'checkbox') {
    input = <input {...common} type="checkbox" className="size-4" checked={Boolean(value)} onChange={(e) => onChange(e.target.checked)} />
  } else if (type === 'icon') {
    // Every icon field is a dropdown of the icons the admin knows, with a preview of the chosen one.
    const current = String(value ?? '')
    const known = ICON_OPTIONS.some((o) => o.value === current)
    input = (
      <div className="flex items-center gap-2">
        <span aria-hidden="true" className="flex size-10 shrink-0 items-center justify-center rounded-lg border border-line bg-surface-muted text-primary-700">
          {current ? <Icon name={current} className="size-5" /> : null}
        </span>
        <select {...common} className={control} value={current} onChange={(e) => onChange(e.target.value)}>
          <option value="">-</option>
          {!known && current && <option value={current}>{current}</option>}
          {ICON_OPTIONS.map((o) => (
            <option key={o.value} value={o.value}>
              {o.label}
            </option>
          ))}
        </select>
      </div>
    )
  } else if (type === 'date') {
    input = <input {...common} type="date" className={control} value={String(value ?? '').slice(0, 10)} onChange={(e) => onChange(e.target.value)} />
  } else if (type === 'datetime') {
    input = <input {...common} type="datetime-local" className={control} value={toLocalInput(value)} onChange={(e) => onChange(fromLocalInput(e.target.value))} />
  } else if (type === 'money') {
    input = <MoneyInput {...common} value={value} onChange={onChange} />
  } else if (type === 'number') {
    input = (
      <input
        {...common}
        type="number"
        className={control}
        value={value === null || value === undefined ? '' : String(value)}
        onChange={(e) => onChange(e.target.value === '' ? null : Number(e.target.value))}
      />
    )
  } else {
    input = <input {...common} type={type === 'password' ? 'password' : 'text'} autoComplete={type === 'password' ? 'new-password' : undefined} className={control} value={String(value ?? '')} onChange={(e) => onChange(e.target.value)} />
  }

  return (
    <div className={type === 'checkbox' ? 'flex items-center gap-2' : 'space-y-1'}>
      <label htmlFor={type === 'checkgroup' ? undefined : id} className="block text-sm text-ink-muted">
        {label}
        {required && <span aria-hidden="true"> *</span>}
      </label>
      {input}
      {hint && !error && (
        <p id={`${id}-hint`} className="text-sm text-ink-muted">
          {hint}
        </p>
      )}
      {error && (
        <p id={`${id}-error`} role="alert" className="text-sm text-danger">
          {error}
        </p>
      )}
    </div>
  )
}

/**
 * A price field: the user types major units ("1500.50"), the form value is whole minor units (150050) like the API.
 * The text is kept locally so "12." and "12.0" can be typed; it follows the value when it changes from outside.
 */
function MoneyInput({ value, onChange, ...aria }: { value: unknown; onChange: (value: unknown) => void; id: string; name: string; required?: boolean; 'aria-invalid'?: boolean; 'aria-describedby'?: string }) {
  const { symbol, currency, digits } = useCurrency()
  const minor = value === null || value === undefined || value === '' ? null : Number(value)
  const [text, setText] = useState(() => (minor === null ? '' : String(toMajor(minor, digits))))
  const [seen, setSeen] = useState(minor)

  const [seenDigits, setSeenDigits] = useState(digits)

  // The currency arrives after the first render (digits change) and a form reset changes the value: re-derive the text then.
  if (digits !== seenDigits || (minor !== seen && minor !== toMinor(text, digits))) {
    setSeenDigits(digits)
    setSeen(minor)
    setText(minor === null ? '' : String(toMajor(minor, digits)))
  }

  return (
    <div className="flex items-center gap-2">
      {(symbol || currency) && <span aria-hidden="true" className="text-ink-muted">{symbol || currency}</span>}
      <input
        {...aria}
        type="number"
        inputMode="decimal"
        min={0}
        step={1 / 10 ** digits}
        className={control}
        value={text}
        onChange={(e) => {
          setText(e.target.value)
          const next = toMinor(e.target.value, digits)
          setSeen(next)
          onChange(next)
        }}
      />
    </div>
  )
}

/**
 * Several checkboxes writing one string[]. With grouped options it renders a matrix: one row per group, one column per
 * distinct label (e.g. rows = modules, columns = view/create/update/delete); a missing cell is simply empty.
 */
function CheckGroup({
  label,
  value,
  options,
  onChange,
  ...aria
}: {
  label: string
  value: string[]
  options: FieldOption[]
  onChange: (value: unknown) => void
  id: string
  name: string
}) {
  // Ids can arrive as numbers (a loaded row) or strings (earlier toggles); compare and store them as strings.
  const current = value.map(String)
  const toggle = (option: FieldOption, on: boolean) => {
    const key = String(option.value)
    onChange(on ? [...current.filter((v) => v !== key), key] : current.filter((v) => v !== key))
  }
  const box = (option: FieldOption, name = option.label) => (
    <input type="checkbox" className="size-4" aria-label={`${label}: ${name}`} checked={current.includes(String(option.value))} onChange={(e) => toggle(option, e.target.checked)} />
  )

  if (!options.some((o) => o.group)) {
    return (
      <div id={aria.id} role="group" aria-label={label} className="flex flex-wrap gap-x-4 gap-y-1">
        {options.map((o) => (
          <label key={o.value} className="flex items-center gap-2">
            {box(o)}
            {o.label}
          </label>
        ))}
      </div>
    )
  }

  const groups = [...new Set(options.map((o) => o.group ?? ''))]
  const columns = [...new Set(options.map((o) => o.label))]

  return (
    <div id={aria.id} className="overflow-x-auto rounded-md border border-line">
      <table className="w-full text-left text-sm">
        <thead className="border-b border-line bg-canvas text-ink-muted">
          <tr>
            <th scope="col" className="px-2 py-1.5" />
            {columns.map((c) => (
              <th key={c} scope="col" className="px-2 py-1.5 font-semibold">
                {c}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {groups.map((group) => (
            <tr key={group} className="border-b border-line last:border-0">
              <th scope="row" className="px-2 py-1.5 font-normal">
                {group}
              </th>
              {columns.map((c) => {
                const option = options.find((o) => (o.group ?? '') === group && o.label === c)

                return <td key={c} className="px-2 py-1.5">{option && box(option, `${group} ${c}`)}</td>
              })}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
