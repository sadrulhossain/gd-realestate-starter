import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { cleanup, fireEvent, render, screen } from '@testing-library/react'
import { useState } from 'react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { LookupField } from './components/LookupField'

const { request } = vi.hoisted(() => ({ request: vi.fn() }))
vi.mock('./api', () => ({ api: { request } }))

afterEach(() => {
  cleanup()
  request.mockReset()
})

function Harness({ initialLabel }: { initialLabel?: string }) {
  const [value, setValue] = useState<unknown>(initialLabel ? 4 : null)

  return (
    <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
      <LookupField label="Property" name="property_id" lookup={{ endpoint: '/v1/properties', label: (p: { title: string }) => p.title }} value={value} initialLabel={initialLabel} onChange={setValue} />
      <output data-testid="value">{String(value)}</output>
    </QueryClientProvider>
  )
}

describe('lookup field', () => {
  it('searches after two characters, picks a result and reports its id', async () => {
    request.mockResolvedValue({ data: [{ id: 9, title: 'Sea view flat' }] })
    render(<Harness />)

    fireEvent.change(screen.getByLabelText('Property'), { target: { value: 's' } })
    await new Promise((r) => setTimeout(r, 350))
    expect(request).not.toHaveBeenCalled()

    fireEvent.change(screen.getByLabelText('Property'), { target: { value: 'sea' } })
    fireEvent.click(await screen.findByRole('button', { name: 'Sea view flat' }))

    expect(request).toHaveBeenCalledWith('GET', '/v1/properties', { query: { q: 'sea' } })
    expect(screen.getByTestId('value').textContent).toBe('9')
    expect(screen.getByTestId('property_id-picked').textContent).toBe('Sea view flat')
  })

  it('shows the current value by name and clears it', () => {
    render(<Harness initialLabel="Old flat" />)
    expect(screen.getByTestId('property_id-picked').textContent).toBe('Old flat')

    fireEvent.click(screen.getByRole('button', { name: 'Clear' }))
    expect(screen.getByTestId('value').textContent).toBe('null')
  })
})
