import { useMutation, useQueryClient } from '@tanstack/react-query'
import { ApiError } from '@gd/api-client'
import { api } from '../api'
import { useAuth } from '../auth/store'
import { nextStatuses, statusLabel } from '../lib/propertyStatus'
import { toast } from './Toast'

/**
 * Row control for a property: a menu of the legal next statuses (`POST /v1/properties/{id}/status`).
 * Hidden without `properties.update` or when the status is final for now (no moves).
 */
export function PropertyStatusAction({ row }: { row: { id: number; status?: unknown; listing_type?: unknown; title?: unknown } }) {
  const queryClient = useQueryClient()
  const allowed = useAuth((s) => s.can)('properties.update')
  const moves = nextStatuses(String(row.status), String(row.listing_type))
  const change = useMutation({
    mutationFn: (status: string) => api.request('POST', `/v1/properties/${row.id}/status`, { body: { status } }),
    onSuccess: () => {
      toast.success('Status changed')
      void queryClient.invalidateQueries({ queryKey: ['crud', '/v1/properties'] })
    },
    onError: (error) => toast.error(error instanceof ApiError && error.status === 422 ? 'That status change is not allowed' : 'Could not change the status'),
  })

  if (!allowed || moves.length === 0) return null

  return (
    <select
      className="field !h-8 !w-auto !py-0 text-sm"
      aria-label={`Change status of ${String(row.title)}`}
      value=""
      disabled={change.isPending}
      onChange={(e) => e.target.value && change.mutate(e.target.value)}
    >
      <option value="">Move to...</option>
      {moves.map((s) => (
        <option key={s} value={s}>
          {statusLabel(s)}
        </option>
      ))}
    </select>
  )
}
