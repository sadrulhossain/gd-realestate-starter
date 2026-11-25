import { describe, expect, it } from 'vitest'
import { blank, payload } from './lib/crud'
import { LEAD_STATUSES, nextLeadStatuses, nextViewingStatuses, statusTone, VIEWING_STATUSES } from './lib/salesStatus'
import { modules } from './modules'
import { reportQuery, REPORTS } from './pages/Reports'

const byKey = (key: string) => modules.find((m) => m.key === key)!

describe('sales module configs', () => {
  it('registers agents, leads and viewings on their /v1 endpoints', () => {
    for (const key of ['agents', 'leads', 'viewings']) {
      expect(byKey(key).endpoint).toBe(`/v1/${key}`)
      expect(byKey(key).path).toBe(`/${key}`)
    }
  })

  it('agents: photo upload on, optional staff link needs users.view', () => {
    const a = byKey('agents')
    expect(a.image).toBe(true)
    expect(a.columns.some((c) => c.key === 'image')).toBe(true)
    expect(a.fields.find((f) => f.name === 'user_id')?.needs).toBe('users.view')
    expect(a.fields.map((f) => f.name)).not.toContain('image')
  })

  it('leads: status and agent are not edit fields, the pipeline panel carries them', () => {
    const l = byKey('leads')
    expect(l.fields.map((f) => f.name)).not.toContain('status')
    const agent = l.fields.find((f) => f.name === 'agent_id')!
    expect([agent.createOnly, agent.needs]).toEqual([true, 'leads.assign'])
    expect(l.editExtra).toBeTypeOf('function')
    expect(l.filters?.map((f) => f.name)).toEqual(['q', 'status', 'source', 'agent_id'])
    expect(l.filters?.find((f) => f.name === 'status')?.options?.map((o) => o.value)).toEqual([...LEAD_STATUSES])
    expect(l.columns.map((c) => c.key)).toContain('status')
  })

  it('leads: an edit never sends agent_id or status (the API prohibits them on PATCH)', () => {
    const l = byKey('leads')
    const editable = l.fields.filter((f) => !f.createOnly)
    const body = payload(editable, { ...blank(l.fields), name: 'Jane', phone: '1', agent_id: 5, status: 'won' })
    expect(body).not.toHaveProperty('agent_id')
    expect(body).not.toHaveProperty('status')
    expect(body).toMatchObject({ name: 'Jane', phone: '1', source: 'phone' })
  })

  it('viewings: property is create-only and searched, the agent select needs view_all, the agenda starts today', () => {
    const v = byKey('viewings')
    const property = v.fields.find((f) => f.name === 'property_id')!
    expect([property.createOnly, property.required]).toEqual([true, true])
    expect(property.lookup?.endpoint).toBe('/v1/properties')
    expect(property.lookup?.label({ ref_code: 'P-000001', title: 'Flat' } as never)).toBe('P-000001 Flat')
    expect(v.fields.find((f) => f.name === 'agent_id')?.needs).toBe('viewings.view_all')
    expect(v.fields.map((f) => f.name)).not.toContain('status')
    const from = v.filters?.find((f) => f.name === 'from')
    expect(from?.initial).toMatch(/^\d{4}-\d{2}-\d{2}$/)
    expect(v.filters?.find((f) => f.name === 'status')?.options?.map((o) => o.value)).toEqual([...VIEWING_STATUSES])
    expect(v.rowExtra).toBeTypeOf('function')
  })

  it('viewings: the lead picker names the current lead when editing', () => {
    const lead = byKey('viewings').fields.find((f) => f.name === 'lead_id')!
    expect(lead.lookup?.initialLabel?.({ lead: { name: 'Jane' } })).toBe('Jane')
    expect(lead.lookup?.initialLabel?.({ lead: null })).toBeUndefined()
  })
})

describe('pipeline menus', () => {
  it('lead moves mirror the API table', () => {
    expect(nextLeadStatuses('new')).toEqual(['contacted', 'lost'])
    expect(nextLeadStatuses('negotiation')).toEqual(['viewing', 'won', 'lost'])
    expect(nextLeadStatuses('won')).toEqual([])
    expect(nextLeadStatuses('lost')).toEqual(['new'])
    expect(nextLeadStatuses('bogus')).toEqual([])
  })

  it('viewing moves: finished viewings are final', () => {
    expect(nextViewingStatuses('scheduled')).toEqual(['confirmed', 'cancelled', 'no_show'])
    expect(nextViewingStatuses('confirmed')).toEqual(['completed', 'cancelled', 'no_show'])
    for (const done of ['completed', 'cancelled', 'no_show']) expect(nextViewingStatuses(done)).toEqual([])
  })

  it('every status has a tone', () => {
    for (const s of [...LEAD_STATUSES, ...VIEWING_STATUSES]) expect(statusTone(s)).toMatch(/^bg-/)
  })
})

describe('reports page helpers', () => {
  it('lists the three API reports', () => {
    expect(REPORTS.map((r) => r.key)).toEqual(['leads', 'viewings', 'properties'])
  })

  it('sends only the dates that are set', () => {
    expect(reportQuery('', '')).toEqual({})
    expect(reportQuery('2030-01-01', '')).toEqual({ from: '2030-01-01' })
    expect(reportQuery('2030-01-01', '2030-01-31')).toEqual({ from: '2030-01-01', to: '2030-01-31' })
  })
})
