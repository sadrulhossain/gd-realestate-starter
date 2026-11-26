import { describe, expect, it } from 'vitest'
import { blank, payload } from './lib/crud'
import { nextLeaseStatuses, nextMaintenanceStatuses } from './lib/rentalStatus'
import { modules } from './modules'
import { RENTAL_REPORTS } from './pages/Reports'

const byKey = (key: string) => modules.find((m) => m.key === key)!

describe('rental module configs', () => {
  it('registers the screens on their /v1 endpoints', () => {
    expect(byKey('tenants').endpoint).toBe('/v1/tenants')
    expect(byKey('leases').endpoint).toBe('/v1/leases')
    expect(byKey('rent_charges').endpoint).toBe('/v1/rent-charges')
    expect(byKey('maintenance').endpoint).toBe('/v1/maintenance')
  })

  it('leases: status is not a field; an edit does not resend property or tenant', () => {
    const l = byKey('leases')
    expect(l.fields.map((f) => f.name)).not.toContain('status')
    const body = payload(l.fields.filter((f) => !f.createOnly), { ...blank(l.fields), start_date: '2026-01-01', property_id: 1 })
    expect(body).not.toHaveProperty('property_id')
    expect(body).not.toHaveProperty('tenant_id')
    expect(body).toMatchObject({ start_date: '2026-01-01', rent_period: 'month', deposit_amount: 0 })
  })

  it('rent: charges cannot be created or deleted, and the list starts on overdue', () => {
    const r = byKey('rent_charges')
    expect([r.canCreate, r.canDelete]).toEqual([false, false])
    expect(r.filters?.find((f) => f.name === 'status')?.initial).toBe('overdue')
    expect(r.fields.map((f) => f.name)).toEqual(['due_date', 'note'])
    expect(r.editExtra).toBeTypeOf('function')
  })

  it('status menus mirror the API tables', () => {
    expect(nextLeaseStatuses('draft')).toEqual(['active', 'cancelled'])
    expect(nextLeaseStatuses('terminated')).toEqual([])
    expect(nextMaintenanceStatuses('resolved')).toEqual(['open'])
  })

  it('has the three rental reports', () => {
    expect(RENTAL_REPORTS.map((r) => r.key)).toEqual(['rent-roll', 'arrears', 'collections'])
  })
})
