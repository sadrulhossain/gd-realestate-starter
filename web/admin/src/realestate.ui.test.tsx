import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, render, screen, cleanup } from '@testing-library/react'
import { useState } from 'react'
import { afterEach, describe, expect, it } from 'vitest'
import { FormField } from './components/FormField'

afterEach(cleanup)

function Harness({ initial }: { initial: unknown }) {
  const [value, setValue] = useState<unknown>(initial)

  return (
    <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
      <FormField label="Price" name="price" type="money" value={value} onChange={setValue} />
      <output data-testid="minor">{String(value)}</output>
    </QueryClientProvider>
  )
}

describe('money field', () => {
  it('shows major units and reports whole minor units', () => {
    render(<Harness initial={150050} />)
    const input = screen.getByLabelText('Price') as HTMLInputElement
    expect(input.value).toBe('1500.5')

    fireEvent.change(input, { target: { value: '12.34' } })
    expect(screen.getByTestId('minor').textContent).toBe('1234')

    fireEvent.change(input, { target: { value: '' } })
    expect(screen.getByTestId('minor').textContent).toBe('null')
  })
})
