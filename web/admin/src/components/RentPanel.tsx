import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { ApiError } from '@gd/api-client'
import { api } from '../api'
import { useAuth } from '../auth/store'
import { fieldErrors } from '../lib/crud'
import { statusLabel } from '../lib/propertyStatus'
import { PAYMENT_METHODS } from '../lib/rentalStatus'
import { FormField } from './FormField'
import { IconButton } from './IconButton'
import { Money } from './Money'
import { StatusBadge } from './StatusBadge'
import { toast } from './Toast'

interface Payment {
  id: number
  amount: number
  paid_on: string
  method: string
  reference: string | null
}

interface Charge {
  id: number
  amount: number
  paid_amount: number
  status: string
  payments: Payment[]
}

const today = () => new Date().toLocaleDateString('en-CA')

/**
 * Inside a rent charge's edit dialog: what is owed, the payments, and a form to record one.
 * Everything here is its own request, so the form's Save button is not involved (buttons are `type="button"`).
 */
export function RentPanel({ chargeId }: { chargeId: number }) {
  const queryClient = useQueryClient()
  const allowed = useAuth((s) => s.can)('rent_charges.update')
  const [amount, setAmount] = useState<unknown>(null)
  const [paidOn, setPaidOn] = useState<unknown>(today())
  const [method, setMethod] = useState<unknown>('cash')
  const [reference, setReference] = useState<unknown>('')
  const [errors, setErrors] = useState<Record<string, string>>({})

  const charge = useQuery({
    queryKey: ['rent-charge', chargeId],
    queryFn: async () => ((await api.request('GET', `/v1/rent-charges/${chargeId}`)) as { data: Charge }).data,
  })

  const refresh = () => {
    void queryClient.invalidateQueries({ queryKey: ['rent-charge', chargeId] })
    void queryClient.invalidateQueries({ queryKey: ['crud', '/v1/rent-charges'] })
  }

  const pay = useMutation({
    mutationFn: () => api.request('POST', `/v1/rent-charges/${chargeId}/payments`, { body: { amount, paid_on: paidOn, method, reference: reference || null } }),
    onSuccess: () => {
      toast.success('Payment recorded')
      setAmount(null)
      setReference('')
      setErrors({})
      refresh()
    },
    onError: (error) => {
      setErrors(fieldErrors(error))
      toast.error(error instanceof ApiError && error.status === 422 ? 'Please fix the highlighted fields' : 'Could not record the payment')
    },
  })
  const reverse = useMutation({
    mutationFn: (id: number) => api.request('DELETE', `/v1/rent-charges/${chargeId}/payments/${id}`),
    onSuccess: () => {
      toast.success('Payment removed')
      refresh()
    },
    onError: () => toast.error('Could not remove the payment'),
  })

  if (charge.isError) return <p role="alert" className="text-danger">Could not load the payments.</p>
  if (!charge.data) return <p className="text-ink-muted">Loading payments...</p>

  const owed = charge.data.status === 'void' ? 0 : charge.data.amount - charge.data.paid_amount

  return (
    <section aria-label="Payments" className="space-y-4 rounded-lg border border-line p-3">
      <div className="flex flex-wrap items-center gap-3 text-sm">
        <StatusBadge status={charge.data.status} />
        <span>
          Paid <Money minor={charge.data.paid_amount} /> of <Money minor={charge.data.amount} />
        </span>
      </div>

      <ul aria-label="Payments made" className="space-y-1 text-sm">
        {charge.data.payments.map((p) => (
          <li key={p.id} className="flex items-center justify-between gap-2 border-s-2 border-line ps-3">
            <span>
              <Money minor={p.amount} /> · {p.paid_on} · {statusLabel(p.method)}
              {p.reference ? ` · ${p.reference}` : ''}
            </span>
            {allowed && <IconButton variant="row-danger" icon="trash" label={`Remove the payment of ${p.paid_on}`} disabled={reverse.isPending} onClick={() => reverse.mutate(p.id)} />}
          </li>
        ))}
        {charge.data.payments.length === 0 && <li className="text-ink-muted">No payments yet.</li>}
      </ul>

      {allowed && owed > 0 && (
        <fieldset className="grid gap-3 sm:grid-cols-2">
          <legend className="mb-2 text-sm text-ink-muted">Record a payment</legend>
          <FormField label="Amount" name="amount" type="money" value={amount} error={errors.amount} onChange={setAmount} />
          <FormField label="Paid on" name="paid_on" type="date" value={paidOn} error={errors.paid_on} onChange={setPaidOn} />
          <FormField label="Method" name="method" type="select" options={PAYMENT_METHODS.map((m) => ({ value: m, label: statusLabel(m) }))} value={method} error={errors.method} onChange={setMethod} />
          <FormField label="Reference" name="reference" value={reference} error={errors.reference} onChange={setReference} />
          <div>
            <IconButton showText variant="secondary" icon="plus" label="Record payment" disabled={!amount || !paidOn || !method || pay.isPending} onClick={() => pay.mutate()} />
          </div>
        </fieldset>
      )}
    </section>
  )
}
