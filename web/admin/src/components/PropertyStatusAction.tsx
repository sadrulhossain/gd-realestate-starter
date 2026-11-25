import { nextStatuses } from '../lib/propertyStatus'
import { RowStatusMenu } from './RowStatusMenu'

/** Row control for a property: the legal next statuses, posted to `/v1/properties/{id}/status`. */
export function PropertyStatusAction({ row }: { row: { id: number; status?: unknown; listing_type?: unknown; title?: unknown } }) {
  return (
    <RowStatusMenu
      label={`Change status of ${String(row.title)}`}
      endpoint={`/v1/properties/${row.id}/status`}
      listEndpoint="/v1/properties"
      permission="properties.update"
      moves={nextStatuses(String(row.status), String(row.listing_type))}
    />
  )
}
