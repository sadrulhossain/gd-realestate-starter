import { useQuery } from '@tanstack/react-query'
import { api } from '../api'
import { formatMoney, fractionDigits } from '../lib/money'

export interface Currency {
  currency: string | null
  symbol: string | null
  digits: number
}

/** The site's default currency from the public settings (cached; money still renders while it loads). */
export function useCurrency(): Currency {
  const settings = useQuery({
    queryKey: ['currency'],
    staleTime: 5 * 60_000,
    queryFn: async () => ((await api.request('GET', '/v1/public/settings')) as { data: { currency?: string | null; currency_symbol?: string | null } }).data,
  })
  const currency = settings.data?.currency ?? null

  return { currency, symbol: settings.data?.currency_symbol ?? null, digits: fractionDigits(currency) }
}

/** A price in a table cell: minor units in, "BDT 1,500.00" out. */
export function Money({ minor }: { minor: unknown }) {
  const { currency, symbol } = useCurrency()

  return <>{formatMoney(minor, currency, symbol)}</>
}
