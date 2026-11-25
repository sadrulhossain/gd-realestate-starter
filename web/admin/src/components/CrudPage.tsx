import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, type ReactNode } from 'react'
import { ApiError } from '@gd/api-client'
import { api } from '../api'
import { useAuth } from '../auth/store'
import { blank, fieldErrors, payload } from '../lib/crud'
import { moveId } from '../lib/reorder'
import type { Listing } from '../types'
import { BilingualField } from './BilingualField'
import { ConfirmDialog, Modal } from './ConfirmDialog'
import { ImagePanel } from './ImagePanel'
import { LookupField } from './LookupField'
import { DataTable, type Column } from './DataTable'
import { FormField, type FieldOption, type FieldType } from './FormField'
import { toast } from './Toast'
import { IconButton } from './IconButton'

export type Row = Record<string, unknown> & { id: number }

export interface CrudField {
  name: string
  label: string
  type?: FieldType
  /** Renders `name` and `name_2nd`, labelled with the locale codes. Text, textarea and richtext only. */
  bilingual?: boolean
  required?: boolean
  hint?: string
  options?: FieldOption[]
  /** Options fetched from the API instead (roles for a user, permissions for a role); `map` turns one item of `data` into an option. */
  optionsFrom?: { endpoint: string; map?: (item: never) => FieldOption; mapAll?: (items: never[]) => FieldOption[] }
  /** Forms with tabs: fields sharing a `tab` name form one tab, in order of first appearance. Give every field a tab or none. */
  tab?: string
  /** Only on the create form: the API rejects it on PATCH (the property of a viewing, the agent of a new lead). */
  createOnly?: boolean
  /** Hidden (and not sent) unless the user holds this permission. */
  needs?: string
  /** A search box over `${endpoint}?q=`: the user picks one result and the value is its id. `initialLabel` names the current value when editing. */
  lookup?: { endpoint: string; label: (item: never) => string; initialLabel?: (values: Record<string, unknown>) => string | undefined }
  /** Starting value on create. */
  initial?: unknown
}

/** A list filter: sent as `?name=value` when not empty. Same shapes as a field (select, text, date). */
export interface CrudFilter {
  name: string
  label: string
  type?: 'select' | 'text' | 'date'
  options?: FieldOption[]
  optionsFrom?: CrudField['optionsFrom']
  initial?: string
}

/**
 * Everything a module needs to get a list + create/edit/delete screen. Add an object to modules.ts; no new page code.
 * Permissions are `${key}.view|create|update|delete`; buttons the user may not use are not rendered.
 */
export interface CrudConfig {
  key: string
  path: string
  title: string
  /** Collection endpoint, e.g. `/v1/faqs`; rows live at `${endpoint}/${id}`. */
  endpoint: string
  columns: Column<Row>[]
  fields: CrudField[]
  /** Singular noun for dialogs: "FAQ". */
  noun: string
  /** Text identifying a row in the delete confirmation. */
  rowLabel?: (row: Row) => string
  /** Seeded, edit-only modules (sections) turn these off. */
  canCreate?: boolean
  canDelete?: boolean
  /**
   * Rows become draggable. Dropping sends the new order of the rows sharing `group` (all rows when omitted) as
   * `PUT endpoint {ids}`. Needs `${key}.update`.
   */
  /** The rows have an image at `${endpoint}/${id}/image` (POST to replace, DELETE to remove); shown in the edit form. */
  image?: boolean
  /** Rows are edited and deleted at `${rowEndpoint}/${id}` when that differs from the list endpoint (shallow routes). */
  rowEndpoint?: string
  /** Fetch the row (`GET ${rowEndpoint}/${id}`) before editing: the list omits relations the form needs (amenity ids). */
  loadOnEdit?: boolean
  /** Filters above the table; changing one returns to page 1. */
  filters?: CrudFilter[]
  /** Extra content under the edit form's fields (a timeline); only for an existing row. */
  editExtra?: (row: Row) => ReactNode
  /** Extra controls in a row's action cell (status menu, links). Render with the row; check permissions inside. */
  rowExtra?: (row: Row) => ReactNode
  reorder?: { endpoint: string; group?: (row: Row) => unknown }
}

/** Forms with rich text or many fields get a wider dialog. */
function formSize(fields: CrudField[]): 'md' | 'lg' | 'xl' {
  if (fields.some((f) => f.type === 'richtext' || f.type === 'checkgroup' || f.tab)) return 'xl'

  return fields.length > 4 ? 'lg' : 'md'
}

export function CrudPage({ config }: { config: CrudConfig }) {
  const queryClient = useQueryClient()
  const can = useAuth((s) => s.can)
  const [page, setPage] = useState(1)
  const [editing, setEditing] = useState<Row | 'new' | null>(null)
  const [deleting, setDeleting] = useState<Row | null>(null)

  const canCreate = config.canCreate !== false && can(`${config.key}.create`)
  const canUpdate = can(`${config.key}.update`)
  const canDelete = config.canDelete !== false && can(`${config.key}.delete`)

  const [filters, setFilters] = useState<Record<string, string>>(() => Object.fromEntries((config.filters ?? []).map((f) => [f.name, f.initial ?? ''])))
  const activeFilters = Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== ''))
  const listKey = ['crud', config.endpoint, page, activeFilters]
  const list = useQuery({
    queryKey: listKey,
    placeholderData: keepPreviousData,
    queryFn: async () => (await api.request('GET', config.endpoint, { query: { page, ...activeFilters } })) as Listing<Row>,
  })

  const move = useMutation({
    mutationFn: (ids: number[]) => api.request('PUT', config.reorder!.endpoint, { body: { ids } }),
    onSuccess: () => void refresh(),
    onError: () => toast.error('Could not save the new order'),
  })

  const onDropRow = (from: Row, to: Row) => {
    const group = config.reorder?.group
    if (group && group(from) !== group(to)) return

    const siblings = group ? rows.filter((r) => group(r) === group(from)) : rows
    const ids = siblings.map((r) => r.id)
    const next = moveId(ids, from.id, to.id)
    if (next !== ids) move.mutate(next)
  }

  const rows = list.data?.data ?? []
  // Menu, role and user edits can change the signed-in user's sidebar, so it reloads after every write.
  const refresh = () => Promise.all([queryClient.invalidateQueries({ queryKey: ['crud', config.endpoint] }), queryClient.invalidateQueries({ queryKey: ['menus'] })])

  const remove = useMutation({
    mutationFn: (row: Row) => api.request('DELETE', `${rowBase(config)}/${row.id}`),
    onSuccess: () => {
      toast.success(`${config.noun} deleted`)
      setDeleting(null)
      void refresh()
    },
    onError: () => toast.error(`Could not delete the ${config.noun.toLowerCase()}`),
  })

  const meta = list.data?.meta
  const actions = (row: Row): ReactNode => (
    <span className="inline-flex items-center gap-2">
      {config.rowExtra?.(row)}
      {canUpdate && (
        <IconButton variant="row" icon="pencil" label="Edit" onClick={() => setEditing(row)} />
      )}
      {canDelete && (
        <IconButton variant="row-danger" icon="trash" label="Delete" onClick={() => setDeleting(row)} />
      )}
    </span>
  )

  return (
    <section className="space-y-4">
      <header className="flex flex-wrap items-center justify-between gap-2">
        <h1 className="text-2xl font-semibold">{config.title}</h1>
        {canCreate && (
          <IconButton showText variant="primary" icon="plus" label={`New ${config.noun.toLowerCase()}`} onClick={() => setEditing('new')} />
        )}
      </header>

      {config.filters && config.filters.length > 0 && (
        <form className="flex flex-wrap items-end gap-3" aria-label="Filters" onSubmit={(e) => e.preventDefault()}>
          {config.filters.map((f) => (
            <div key={f.name} className="min-w-40">
              <OptionsField
                field={{ name: f.name, label: f.label, type: f.type ?? 'text', options: f.options, optionsFrom: f.optionsFrom }}
                value={filters[f.name]}
                values={filters}
                onChange={(v) => {
                  setFilters((prev) => ({ ...prev, [f.name]: String(v ?? '') }))
                  setPage(1)
                }}
              />
            </div>
          ))}
        </form>
      )}

      {list.isError ? (
        <p role="alert" className="text-danger">
          Could not load {config.title.toLowerCase()}.
        </p>
      ) : (
        <DataTable
          columns={config.columns}
          rows={rows}
          rowKey={(r) => r.id}
          loading={list.isPending}
          actions={canUpdate || canDelete ? actions : undefined}
          onDropRow={config.reorder && canUpdate ? onDropRow : undefined}
        />
      )}

      {meta && meta.last_page > 1 && (
        <nav className="flex items-center justify-end gap-3" aria-label="Pagination">
          <IconButton variant="secondary" icon="chevron-left" label="Previous" disabled={page <= 1} onClick={() => setPage(page - 1)} />
          <span className="text-ink-muted">
            {meta.current_page} / {meta.last_page}
          </span>
          <IconButton variant="secondary" icon="chevron-right" label="Next" disabled={page >= meta.last_page} onClick={() => setPage(page + 1)} />
        </nav>
      )}

      <Modal size={formSize(config.fields)} open={editing !== null} title={editing === 'new' ? `New ${config.noun.toLowerCase()}` : `Edit ${config.noun.toLowerCase()}`} onClose={() => setEditing(null)}>
        {editing !== null && (
          <CrudFormLoader
            key={editing === 'new' ? 'new' : editing.id}
            config={config}
            row={editing === 'new' ? null : editing}
            onDone={() => {
              setEditing(null)
              void refresh()
            }}
            onCancel={() => setEditing(null)}
          />
        )}
      </Modal>

      <ConfirmDialog
        open={deleting !== null}
        title={`Delete ${config.noun.toLowerCase()}`}
        message={`Delete ${deleting && config.rowLabel ? `"${config.rowLabel(deleting)}"` : `this ${config.noun.toLowerCase()}`}? This cannot be undone.`}
        confirmLabel="Delete"
        danger
        busy={remove.isPending}
        onConfirm={() => deleting && remove.mutate(deleting)}
        onCancel={() => setDeleting(null)}
      />
    </section>
  )
}

/** Where one row lives: the list endpoint unless the config says otherwise. */
const rowBase = (config: CrudConfig) => config.rowEndpoint ?? config.endpoint

/** Loads the full row first when the config asks for it, then shows the form. */
function CrudFormLoader(props: { config: CrudConfig; row: Row | null; onDone: () => void; onCancel: () => void }) {
  const { config, row } = props
  const detail = useQuery({
    queryKey: ['crud-row', rowBase(config), row?.id],
    enabled: Boolean(config.loadOnEdit && row),
    gcTime: 0,
    queryFn: async () => ((await api.request('GET', `${rowBase(config)}/${row!.id}`)) as { data: Row }).data,
  })

  if (config.loadOnEdit && row) {
    if (detail.isError) return <p role="alert" className="text-danger">Could not load the {config.noun.toLowerCase()}.</p>
    if (!detail.data) return <p className="text-ink-muted">Loading...</p>

    return <CrudForm {...props} row={detail.data} />
  }

  return <CrudForm {...props} />
}

function CrudForm({ config, row, onDone, onCancel }: { config: CrudConfig; row: Row | null; onDone: () => void; onCancel: () => void }) {
  const queryClient = useQueryClient()
  const [values, setValues] = useState<Record<string, unknown>>(() => ({ ...blank(config.fields), ...(row ?? {}) }))
  const [errors, setErrors] = useState<Record<string, string>>({})
  const set = (name: string, value: unknown) => setValues((v) => ({ ...v, [name]: value }))
  const can = useAuth((s) => s.can)
  // Fields the API accepts for this user and this operation (create vs edit).
  const fields = config.fields.filter((f) => !(f.createOnly && row) && (!f.needs || can(f.needs)))
  const tabs = [...new Set(fields.flatMap((f) => (f.tab ? [f.tab] : [])))]
  const [tab, setTab] = useState(tabs[0])
  // A tab is flagged when one of its fields failed validation (the error may be on a field of a tab that is not showing).
  const tabHasError = (name: string) => fields.some((f) => f.tab === name && (f.bilingual ? [f.name, `${f.name}_2nd`] : [f.name]).some((n) => errors[n]))
  const shown = tabs.length ? fields.filter((f) => f.tab === tab) : fields

  const save = useMutation({
    mutationFn: () =>
      row
        ? api.request('PATCH', `${rowBase(config)}/${row.id}`, { body: payload(fields, values) })
        : api.request('POST', config.endpoint, { body: payload(fields, values) }),
    onSuccess: () => {
      toast.success(`${config.noun} saved`)
      onDone()
    },
    onError: (error) => {
      const found = fieldErrors(error)
      setErrors(found)
      const firstBad = tabs.find((t) => fields.some((f) => f.tab === t && (f.bilingual ? [f.name, `${f.name}_2nd`] : [f.name]).some((n) => found[n])))
      if (firstBad && !fields.some((f) => f.tab === tab && (f.bilingual ? [f.name, `${f.name}_2nd`] : [f.name]).some((n) => found[n]))) setTab(firstBad)
      toast.error(error instanceof ApiError && error.status === 422 ? 'Please fix the highlighted fields' : `Could not save the ${config.noun.toLowerCase()}`)
    },
  })

  return (
    <form
      className="space-y-3"
      noValidate
      onSubmit={(e) => {
        e.preventDefault()
        setErrors({})
        save.mutate()
      }}
    >
      {tabs.length > 0 && (
        <div role="tablist" aria-label={`${config.noun} sections`} className="flex flex-wrap gap-1 border-b border-line">
          {tabs.map((t) => (
            <button
              key={t}
              type="button"
              role="tab"
              aria-selected={t === tab}
              onClick={() => setTab(t)}
              className={`-mb-px border-b-2 px-3 py-2 text-sm ${t === tab ? 'border-primary-600 font-semibold text-primary-700' : 'border-transparent text-ink-muted hover:text-ink'}`}
            >
              {t}
              {tabHasError(t) && <span role="img" aria-label="has errors" className="ms-1 text-danger">!</span>}
            </button>
          ))}
        </div>
      )}
      {shown.map((f) =>
        f.bilingual ? (
          <BilingualField
            key={f.name}
            label={f.label}
            name={f.name}
            type={f.type === 'textarea' || f.type === 'richtext' ? f.type : 'text'}
            required={f.required}
            values={values}
            errors={errors}
            onChange={set}
          />
        ) : (
          <OptionsField key={f.name} field={f} value={values[f.name]} values={values} error={errors[f.name]} onChange={(v) => set(f.name, v)} />
        ),
      )}
      {config.image &&
        (row ? (
          <ImagePanel endpoint={`${rowBase(config)}/${row.id}/image`} image={row.image as { url: string } | null | undefined} onChange={() => void queryClient.invalidateQueries({ queryKey: ['crud', config.endpoint] })} />
        ) : (
          <p className="text-sm text-ink-muted">Save first, then add an image.</p>
        ))}
      {row && config.editExtra?.(row)}
      <div className="flex justify-end gap-2 pt-2">
        <IconButton showText variant="secondary" icon="x" label="Cancel" onClick={onCancel} />
        <IconButton showText type="submit" variant="primary" icon="save" label="Save" disabled={save.isPending} />
      </div>
    </form>
  )
}

/** A plain field whose options may come from the API. */
function OptionsField({ field, value, values, error, onChange }: { field: CrudField; value: unknown; values: Record<string, unknown>; error?: string; onChange: (value: unknown) => void }) {
  const remote = field.optionsFrom
  const options = useQuery({
    queryKey: ['options', remote?.endpoint],
    enabled: remote !== undefined,
    staleTime: 60_000,
    queryFn: async () => {
      const items = ((await api.request('GET', remote!.endpoint)) as { data: never[] }).data

      return remote!.mapAll ? remote!.mapAll(items) : items.map(remote!.map!)
    },
  })

  if (field.lookup) {
    return <LookupField label={field.label} name={field.name} required={field.required} hint={field.hint} lookup={field.lookup} value={value} initialLabel={field.lookup.initialLabel?.(values)} error={error} onChange={onChange} />
  }

  return (
    <FormField
      label={field.label}
      name={field.name}
      type={field.type}
      required={field.required}
      hint={field.hint}
      options={remote ? options.data : field.options}
      value={value}
      error={error}
      onChange={onChange}
    />
  )
}
