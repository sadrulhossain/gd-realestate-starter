import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { ApiError } from '@gd/api-client'
import { api } from '../api'
import { useAuth } from '../auth/store'
import { statusLabel } from '../lib/propertyStatus'
import { nextLeadStatuses } from '../lib/salesStatus'
import { IconButton } from './IconButton'
import { StatusBadge } from './StatusBadge'
import { toast } from './Toast'

interface Note {
  id: number
  kind: 'note' | 'status' | 'assign'
  body: string
  user: { name: string } | null
  created_at: string
}

interface LeadDetail {
  id: number
  status: string
  agent_id: number | null
  notes: Note[]
}

const when = (iso: string) => new Date(iso).toLocaleString('en', { dateStyle: 'medium', timeStyle: 'short' })

/**
 * Inside a lead's edit dialog: pipeline status, assignment and the notes timeline.
 * Everything here is its own request, so the form's Save button is not involved (buttons are `type="button"`).
 */
export function LeadPanel({ leadId }: { leadId: number }) {
  const queryClient = useQueryClient()
  const can = useAuth((s) => s.can)
  const [next, setNext] = useState('')
  const [reason, setReason] = useState('')
  // null = untouched: the select shows the lead's current agent until the user picks something.
  const [agent, setAgent] = useState<string | null>(null)
  const [note, setNote] = useState('')

  const lead = useQuery({
    queryKey: ['lead', leadId],
    queryFn: async () => ((await api.request('GET', `/v1/leads/${leadId}`)) as { data: LeadDetail }).data,
  })
  const agents = useQuery({
    queryKey: ['options', '/v1/agents'],
    enabled: can('leads.assign'),
    staleTime: 60_000,
    queryFn: async () => ((await api.request('GET', '/v1/agents')) as { data: { id: number; name: string }[] }).data,
  })

  const refresh = () => {
    void queryClient.invalidateQueries({ queryKey: ['lead', leadId] })
    void queryClient.invalidateQueries({ queryKey: ['crud', '/v1/leads'] })
  }
  const failed = (what: string) => (error: unknown) =>
    toast.error(error instanceof ApiError && error.status === 422 ? (Object.values(error.details ?? {})[0]?.[0] ?? `Could not ${what}`) : `Could not ${what}`)

  const move = useMutation({
    mutationFn: () => api.request('POST', `/v1/leads/${leadId}/status`, { body: { status: next, ...(next === 'lost' ? { lost_reason: reason } : {}) } }),
    onSuccess: () => {
      setNext('')
      setReason('')
      refresh()
    },
    onError: failed('change the status'),
  })
  const assign = useMutation({
    mutationFn: () => api.request('POST', `/v1/leads/${leadId}/assign`, { body: { agent_id: chosenAgent === '' ? null : Number(chosenAgent) } }),
    onSuccess: () => {
      toast.success('Lead assigned')
      refresh()
    },
    onError: failed('assign the lead'),
  })
  const addNote = useMutation({
    mutationFn: () => api.request('POST', `/v1/leads/${leadId}/notes`, { body: { body: note } }),
    onSuccess: () => {
      setNote('')
      refresh()
    },
    onError: failed('add the note'),
  })

  const chosenAgent = agent ?? String(lead.data?.agent_id ?? '')

  if (lead.isError) return <p role="alert" className="text-danger">Could not load the lead history.</p>
  if (!lead.data) return <p className="text-ink-muted">Loading history...</p>

  const moves = nextLeadStatuses(lead.data.status)
  const canMove = can('leads.update') && moves.length > 0

  return (
    <section aria-label="Lead pipeline" className="space-y-4 rounded-lg border border-line p-3">
      <div className="flex flex-wrap items-center gap-3">
        <span className="text-sm text-ink-muted">Status</span>
        <StatusBadge status={lead.data.status} />
        {canMove && (
          <>
            <select className="field !h-8 !w-auto !py-0 text-sm" aria-label="Move lead to" value={next} onChange={(e) => setNext(e.target.value)}>
              <option value="">Move to...</option>
              {moves.map((s) => (
                <option key={s} value={s}>
                  {statusLabel(s)}
                </option>
              ))}
            </select>
            {next === 'lost' && <input className="field !h-8 !w-56 text-sm" aria-label="Reason lost" placeholder="Why was it lost?" value={reason} onChange={(e) => setReason(e.target.value)} />}
            <IconButton showText variant="secondary" icon="check" label="Change status" disabled={!next || move.isPending || (next === 'lost' && !reason.trim())} onClick={() => move.mutate()} />
          </>
        )}
      </div>

      {can('leads.assign') && (
        <div className="flex flex-wrap items-center gap-3">
          <label htmlFor={`assign-${leadId}`} className="text-sm text-ink-muted">
            Agent
          </label>
          <select id={`assign-${leadId}`} className="field !h-8 !w-auto !py-0 text-sm" value={chosenAgent} onChange={(e) => setAgent(e.target.value)}>
            <option value="">Unassigned</option>
            {agents.data?.map((a) => (
              <option key={a.id} value={a.id}>
                {a.name}
              </option>
            ))}
          </select>
          <IconButton showText variant="secondary" icon="user" label="Assign" disabled={assign.isPending} onClick={() => assign.mutate()} />
        </div>
      )}

      {can('leads.update') && (
        <div className="space-y-2">
          <label htmlFor={`note-${leadId}`} className="block text-sm text-ink-muted">
            Add a note
          </label>
          <textarea id={`note-${leadId}`} rows={2} className="field" maxLength={2000} value={note} onChange={(e) => setNote(e.target.value)} />
          <IconButton showText variant="secondary" icon="plus" label="Add note" disabled={!note.trim() || addNote.isPending} onClick={() => addNote.mutate()} />
        </div>
      )}

      <ol aria-label="Timeline" className="space-y-2 text-sm">
        {[...lead.data.notes].reverse().map((n) => (
          <li key={n.id} className="border-s-2 border-line ps-3">
            <p className={n.kind === 'note' ? '' : 'text-ink-muted'}>{n.body}</p>
            <p className="text-xs text-ink-muted">
              {n.user?.name ?? 'System'} · {when(n.created_at)}
            </p>
          </li>
        ))}
        {lead.data.notes.length === 0 && <li className="text-ink-muted">No history yet.</li>}
      </ol>
    </section>
  )
}
