import { describe, expect, it } from 'vitest'
import { blank, payload } from './lib/crud'
import { locationOptions, locationPaths } from './lib/locations'
import { formatMoney, fractionDigits, toMajor, toMinor } from './lib/money'
import { nextStatuses } from './lib/propertyStatus'
import { modules } from './modules'
import { unitsConfig } from './unitsConfig'

const byKey = (key: string) => modules.find((m) => m.key === key)!

describe('money helper', () => {
  it('converts between minor and major units without float drift', () => {
    expect(toMinor('1500.50')).toBe(150050)
    expect(toMinor(19.99)).toBe(1999)
    expect(toMinor('0.07')).toBe(7)
    expect(toMinor(12.345)).toBe(1235)
    expect(toMinor('', 2)).toBeNull()
    expect(toMinor('abc')).toBeNull()
    expect(toMajor(150050)).toBe(1500.5)
    expect(toMinor(500, 0)).toBe(500)
  })

  it('reads fraction digits from the currency, defaulting to 2', () => {
    expect(fractionDigits('USD')).toBe(2)
    expect(fractionDigits('JPY')).toBe(0)
    expect(fractionDigits(null)).toBe(2)
    expect(fractionDigits('not a code')).toBe(2)
  })

  it('formats a list cell with the symbol or code', () => {
    expect(formatMoney(150050, 'USD', '$')).toBe('$ 1,500.50')
    expect(formatMoney(150050, 'USD')).toBe('USD 1,500.50')
    expect(formatMoney(null, 'USD')).toBe('')
  })
})

describe('property status menu', () => {
  it('offers only legal moves, sold for sale and rented for rent', () => {
    expect(nextStatuses('draft', 'sale')).toEqual(['available', 'archived'])
    expect(nextStatuses('available', 'sale')).toEqual(['draft', 'under_offer', 'sold', 'archived'])
    expect(nextStatuses('available', 'rent')).toEqual(['draft', 'under_offer', 'rented', 'archived'])
    expect(nextStatuses('archived', 'sale')).toEqual(['draft'])
    expect(nextStatuses('nonsense', 'sale')).toEqual([])
  })
})

describe('location paths', () => {
  const items = [
    { id: 1, parent_id: null, name: 'Bangladesh' },
    { id: 2, parent_id: 1, name: 'Dhaka' },
    { id: 3, parent_id: 2, name: 'Gulshan' },
  ]

  it('builds the chain and sorts children after their parent', () => {
    expect(locationPaths(items).get(3)).toBe('Bangladesh > Dhaka > Gulshan')
    expect(locationOptions(items).map((o) => o.label)).toEqual(['Bangladesh', 'Bangladesh > Dhaka', 'Bangladesh > Dhaka > Gulshan'])
  })

  it('survives a cycle', () => {
    expect(locationPaths([{ id: 1, parent_id: 2, name: 'A' }, { id: 2, parent_id: 1, name: 'B' }]).get(1)).toBe('B > A')
  })
})

describe('real estate module configs', () => {
  it('registers the catalogue modules with permission-style keys', () => {
    for (const key of ['locations', 'property_types', 'amenities', 'properties', 'developers', 'projects']) expect(byKey(key), key).toBeDefined()
    expect(byKey('property_types').path).toBe('/property-types')
    expect(byKey('property_types').endpoint).toBe('/v1/property-types')
  })

  it('properties: tabbed, status is not a form field, price is money, amenities load with the row', () => {
    const p = byKey('properties')
    const names = p.fields.map((f) => f.name)
    expect(names).not.toContain('status')
    expect(p.fields.every((f) => f.tab)).toBe(true)
    expect([...new Set(p.fields.map((f) => f.tab))]).toEqual(['Basics', 'Details', 'Description', 'Place', 'Amenities', 'SEO'])
    expect(p.fields.find((f) => f.name === 'price')?.type).toBe('money')
    expect(p.fields.find((f) => f.name === 'amenity_ids')?.type).toBe('checkgroup')
    expect(p.loadOnEdit).toBe(true)
    expect(p.rowExtra).toBeTypeOf('function')
  })

  it('properties: a price in major units is sent as minor units, blank optional numbers are left out', () => {
    const p = byKey('properties')
    const values = { ...blank(p.fields), title: 'Flat', price: toMinor('1500.50'), beds: null, amenity_ids: ['1', '2'] }
    const body = payload(p.fields, values)
    expect(body).toMatchObject({ title: 'Flat', price: 150050, amenity_ids: ['1', '2'], listing_type: 'sale' })
    expect(body).not.toHaveProperty('beds')
    expect(body).not.toHaveProperty('status')
  })

  it('projects: every field is in a tab, location is required and units open on their own screen', () => {
    const p = byKey('projects')
    expect(p.fields.every((f) => f.tab)).toBe(true)
    expect(p.fields.find((f) => f.name === 'location_id')?.required).toBe(true)
    expect(p.rowExtra).toBeTypeOf('function')
  })

  it('units: list under the project, edit at /v1/units, permission prefix project_units', () => {
    const u = unitsConfig('7')
    expect(u.key).toBe('project_units')
    expect(u.endpoint).toBe('/v1/projects/7/units')
    expect(u.rowEndpoint).toBe('/v1/units')
    expect(u.fields.find((f) => f.name === 'price')?.type).toBe('money')
    expect(blank(u.fields)).toMatchObject({ status: 'available', area_unit: 'sqft' })
  })

  it('has unique field names in every module', () => {
    for (const m of [...modules, unitsConfig('1')]) {
      const names = m.fields.map((f) => f.name)
      expect(new Set(names).size, m.key).toBe(names.length)
    }
  })
})
