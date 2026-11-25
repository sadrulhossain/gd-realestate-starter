import type { CrudConfig } from './components/CrudPage'
import { Money } from './components/Money'
import { UnitPublishAction } from './components/UnitPublishAction'
import { statusLabel } from './lib/propertyStatus'

export const UNIT_STATUSES = ['available', 'reserved', 'sold']

/**
 * Units are shallow routes: list and create under the project, edit and delete at `/v1/units/{id}`.
 * Not in `modules` because the route carries the project id (`/projects/:id/units`).
 */
export const unitsConfig = (projectId: string): CrudConfig => ({
  key: 'project_units',
  path: '/projects/:id/units',
  title: 'Units',
  noun: 'Unit',
  endpoint: `/v1/projects/${projectId}/units`,
  rowEndpoint: '/v1/units',
  rowLabel: (r) => String(r.code),
  rowExtra: (r) => <UnitPublishAction row={r} listEndpoint={`/v1/projects/${projectId}/units`} />,
  columns: [
    { key: 'code', header: 'Code' },
    { key: 'property_type', header: 'Type', render: (r) => String((r.property_type as { name?: string } | null)?.name ?? '') },
    { key: 'beds', header: 'Beds' },
    { key: 'area', header: 'Area', render: (r) => (r.area ? `${String(r.area)} ${String(r.area_unit)}` : '') },
    { key: 'price', header: 'Price', render: (r) => <Money minor={r.price} /> },
    { key: 'status', header: 'Status', render: (r) => statusLabel(String(r.status)) },
  ],
  fields: [
    { name: 'code', label: 'Unit code', required: true, hint: 'Unique within the project, for example A-101.' },
    { name: 'name', label: 'Name', bilingual: true },
    { name: 'property_type_id', label: 'Property type', type: 'select', required: true, optionsFrom: { endpoint: '/v1/property-types', map: (t: { id: number; name: string }) => ({ value: t.id, label: t.name }) } },
    { name: 'beds', label: 'Bedrooms', type: 'number' },
    { name: 'baths', label: 'Bathrooms', type: 'number' },
    { name: 'area', label: 'Area', type: 'number' },
    { name: 'area_unit', label: 'Area unit', type: 'select', initial: 'sqft', options: [{ value: 'sqft', label: 'sq ft' }, { value: 'sqm', label: 'sq m' }] },
    { name: 'floor', label: 'Floor', type: 'number' },
    { name: 'price', label: 'Price', type: 'money', required: true },
    { name: 'status', label: 'Status', type: 'select', initial: 'available', options: UNIT_STATUSES.map((s) => ({ value: s, label: statusLabel(s) })) },
  ],
})
