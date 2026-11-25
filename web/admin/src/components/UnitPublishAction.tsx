import { useMutation, useQueryClient } from '@tanstack/react-query'
import { ApiError } from '@gd/api-client'
import { Link } from 'react-router'
import { api } from '../api'
import { useAuth } from '../auth/store'
import { IconButton } from './IconButton'
import { toast } from './Toast'

/**
 * Row control for a unit: "Publish as property" (`POST /v1/units/{id}/publish`) copies it into one draft property.
 * Once published the unit links to that property's list entry instead. Needs `project_units.update` and `properties.create`.
 */
export function UnitPublishAction({ row, listEndpoint }: { row: { id: number; code?: unknown; property_id?: unknown }; listEndpoint: string }) {
  const queryClient = useQueryClient()
  const can = useAuth((s) => s.can)
  const publish = useMutation({
    mutationFn: () => api.request('POST', `/v1/units/${row.id}/publish`),
    onSuccess: () => {
      toast.success('Draft property created')
      void queryClient.invalidateQueries({ queryKey: ['crud', listEndpoint] })
    },
    onError: (error) => toast.error(error instanceof ApiError && error.status === 422 ? 'This unit is already published' : 'Could not publish the unit'),
  })

  if (row.property_id) {
    return can('properties.view') ? (
      <Link className="text-sm text-primary-700 underline" to="/properties">
        Property #{String(row.property_id)}
      </Link>
    ) : null
  }

  if (!can('project_units.update') || !can('properties.create')) return null

  return <IconButton variant="row" icon="upload" label={`Publish unit ${String(row.code)} as property`} disabled={publish.isPending} onClick={() => publish.mutate()} />
}
