import { useMutation, useQueryClient } from '@tanstack/react-query'
import { ApiError } from '@gd/api-client'
import { api } from '../api'
import { useAuth } from '../auth/store'
import { statusLabel } from '../lib/propertyStatus'
import { toast } from './Toast'

/**
 * A "Move to..." menu for a workflow status: POSTs `{status}` to `endpoint` and refreshes the list at `listEndpoint`.
 * `moves` is what the UI thinks is legal (the API answers 422 otherwise). Hidden without `permission` or without moves.
 */
export function RowStatusMenu({
  label,
  endpoint,
  listEndpoint,
  permission,
  moves,
}: {
  label: string
  endpoint: string
  listEndpoint: string
  permission: string
  moves: string[]
}) {
  const queryClient = useQueryClient()
  const allowed = useAuth((s) => s.can)(permission)
  const change = useMutation({
    mutationFn: (status: string) => api.request('POST', endpoint, { body: { status } }),
    onSuccess: () => {
      toast.success('Status changed')
      void queryClient.invalidateQueries({ queryKey: ['crud', listEndpoint] })
    },
    onError: (error) => toast.error(error instanceof ApiError && error.status === 422 ? 'That status change is not allowed' : 'Could not change the status'),
  })

  if (!allowed || moves.length === 0) return null

  return (
    <select className="field !h-8 !w-auto !py-0 text-sm" aria-label={label} value="" disabled={change.isPending} onChange={(e) => e.target.value && change.mutate(e.target.value)}>
      <option value="">Move to...</option>
      {moves.map((s) => (
        <option key={s} value={s}>
          {statusLabel(s)}
        </option>
      ))}
    </select>
  )
}
