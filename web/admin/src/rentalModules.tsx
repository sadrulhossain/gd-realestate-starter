import type { CrudConfig } from './components/CrudPage'
import { Money } from './components/Money'
import { RentPanel } from './components/RentPanel'
import { RowStatusMenu } from './components/RowStatusMenu'
import { StatusBadge } from './components/StatusBadge'
import { statusLabel } from './lib/propertyStatus'
import { CHARGE_STATUSES, LEASE_STATUSES, MAINTENANCE_STATUSES, nextLeaseStatuses, nextMaintenanceStatuses, PRIORITIES } from './lib/rentalStatus'

/**
 * The optional rental screens. The API removes their menus and permissions when RENTALS_ENABLED=false,
 * so they disappear for users without any change here.
 */

const options = (values: readonly string[]) => values.map((v) => ({ value: v, label: statusLabel(v) }))
const text = (value: unknown) => String(value ?? '')

type Property = { ref_code: string; title: string }
const propertyName = (p: unknown) => (p ? `${(p as Property).ref_code} ${(p as Property).title}` : '')

const propertyLookup = {
  endpoint: '/v1/properties',
  label: (p: Property) => `${p.ref_code} ${p.title}`,
  initialLabel: (v: Record<string, unknown>) => propertyName(v.property) || undefined,
}

export const rentalModules: CrudConfig[] = [
  {
    key: 'tenants',
    path: '/tenants',
    title: 'Tenants',
    noun: 'Tenant',
    endpoint: '/v1/tenants',
    rowLabel: (r) => text(r.name),
    filters: [{ name: 'q', label: 'Search', type: 'text' }],
    columns: [
      { key: 'name', header: 'Name' },
      { key: 'phone', header: 'Phone' },
      { key: 'email', header: 'Email' },
    ],
    fields: [
      { name: 'name', label: 'Name', required: true },
      { name: 'phone', label: 'Phone' },
      { name: 'email', label: 'Email' },
      { name: 'id_number', label: 'ID number' },
      { name: 'address', label: 'Address' },
      { name: 'notes', label: 'Notes', type: 'textarea' },
    ],
  },
  {
    key: 'leases',
    path: '/leases',
    title: 'Leases',
    noun: 'Lease',
    endpoint: '/v1/leases',
    rowLabel: (r) => `${text((r.tenant as { name?: string } | null)?.name)}, ${propertyName(r.property)}`,
    rowExtra: (r) => (
      <RowStatusMenu
        label={`Change status of the lease of ${text((r.tenant as { name?: string } | null)?.name)}`}
        endpoint={`/v1/leases/${r.id}/status`}
        listEndpoint="/v1/leases"
        permission="leases.update"
        moves={nextLeaseStatuses(text(r.status))}
      />
    ),
    filters: [
      { name: 'q', label: 'Tenant or property code', type: 'text' },
      { name: 'status', label: 'Status', type: 'select', options: options(LEASE_STATUSES) },
    ],
    columns: [
      { key: 'property', header: 'Property', render: (r) => propertyName(r.property) },
      { key: 'tenant', header: 'Tenant', render: (r) => text((r.tenant as { name?: string } | null)?.name) },
      { key: 'start_date', header: 'Term', render: (r) => `${text(r.start_date)} to ${text(r.ended_on ?? r.end_date)}` },
      { key: 'rent_amount', header: 'Rent', render: (r) => (<><Money minor={r.rent_amount} /> / {text(r.rent_period)}</>) },
      { key: 'status', header: 'Status', render: (r) => <StatusBadge status={r.status} /> },
    ],
    fields: [
      { name: 'property_id', label: 'Property', required: true, createOnly: true, hint: 'A rent listing. It cannot be changed later.', lookup: propertyLookup },
      {
        name: 'tenant_id',
        label: 'Tenant',
        required: true,
        createOnly: true,
        lookup: { endpoint: '/v1/tenants', label: (t: { name: string }) => t.name },
      },
      { name: 'start_date', label: 'Starts', type: 'date', required: true, hint: 'Terms are fixed once the lease is active.' },
      { name: 'end_date', label: 'Ends', type: 'date', required: true },
      { name: 'rent_amount', label: 'Rent per period', type: 'money', required: true },
      { name: 'rent_period', label: 'Period', type: 'select', initial: 'month', options: [{ value: 'month', label: 'Month' }, { value: 'year', label: 'Year' }] },
      { name: 'deposit_amount', label: 'Deposit', type: 'money', initial: 0 },
      { name: 'notes', label: 'Notes', type: 'textarea' },
    ],
  },
  {
    // Charges come from the lease schedule: no create or delete. Payments are recorded in the edit dialog.
    key: 'rent_charges',
    path: '/rent-charges',
    title: 'Rent',
    noun: 'Rent charge',
    endpoint: '/v1/rent-charges',
    canCreate: false,
    canDelete: false,
    loadOnEdit: true,
    filters: [
      { name: 'status', label: 'Status', type: 'select', initial: 'overdue', options: options(CHARGE_STATUSES) },
      { name: 'from', label: 'Due from', type: 'date' },
      { name: 'to', label: 'Due to', type: 'date' },
    ],
    columns: [
      { key: 'due_date', header: 'Due' },
      { key: 'tenant', header: 'Tenant', render: (r) => text((r.lease as { tenant?: { name?: string } } | null)?.tenant?.name) },
      { key: 'property', header: 'Property', render: (r) => propertyName((r.lease as { property?: Property } | null)?.property) },
      { key: 'amount', header: 'Amount', render: (r) => <Money minor={r.amount} /> },
      { key: 'paid_amount', header: 'Paid', render: (r) => <Money minor={r.paid_amount} /> },
      { key: 'status', header: 'Status', render: (r) => <StatusBadge status={r.overdue ? 'overdue' : r.status} /> },
    ],
    fields: [
      { name: 'due_date', label: 'Due date', type: 'date', required: true },
      { name: 'note', label: 'Note' },
    ],
    editExtra: (r) => <RentPanel chargeId={r.id} />,
  },
  {
    key: 'maintenance',
    path: '/maintenance',
    title: 'Maintenance',
    noun: 'Request',
    endpoint: '/v1/maintenance',
    rowLabel: (r) => text(r.title),
    rowExtra: (r) => (
      <RowStatusMenu
        label={`Change status of ${text(r.title)}`}
        endpoint={`/v1/maintenance/${r.id}/status`}
        listEndpoint="/v1/maintenance"
        permission="maintenance.update"
        moves={nextMaintenanceStatuses(text(r.status))}
      />
    ),
    filters: [
      { name: 'status', label: 'Status', type: 'select', options: options(MAINTENANCE_STATUSES) },
      { name: 'priority', label: 'Priority', type: 'select', options: options(PRIORITIES) },
    ],
    columns: [
      { key: 'title', header: 'Request' },
      { key: 'property', header: 'Property', render: (r) => propertyName(r.property) },
      { key: 'priority', header: 'Priority', render: (r) => statusLabel(text(r.priority)) },
      { key: 'cost', header: 'Cost', render: (r) => <Money minor={r.cost} /> },
      { key: 'status', header: 'Status', render: (r) => <StatusBadge status={r.status} /> },
    ],
    fields: [
      { name: 'property_id', label: 'Property', required: true, createOnly: true, lookup: propertyLookup },
      {
        name: 'lease_id',
        label: 'Lease',
        hint: 'Optional. Search by tenant name or property code.',
        lookup: { endpoint: '/v1/leases', label: (l: { property: Property; tenant: { name: string } }) => `${l.tenant.name}, ${l.property.ref_code}` },
      },
      { name: 'title', label: 'Title', required: true },
      { name: 'description', label: 'Description', type: 'textarea' },
      { name: 'priority', label: 'Priority', type: 'select', initial: 'normal', options: options(PRIORITIES) },
      { name: 'cost', label: 'Cost', type: 'money' },
    ],
  },
]
