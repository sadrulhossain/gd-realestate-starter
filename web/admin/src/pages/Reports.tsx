import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { api } from '../api'
import { getValidToken } from '../auth/oauth'
import { IconButton } from '../components/IconButton'
import { toast } from '../components/Toast'
import { config } from '../lib/config'

export const REPORTS = [
  { key: 'leads', label: 'Leads by agent', dates: 'Leads received between the dates.' },
  { key: 'viewings', label: 'Viewings by agent', dates: 'Viewings starting between the dates.' },
  { key: 'properties', label: 'Properties by status', dates: 'Counts every property; the dates do not apply.' },
] as const

interface ReportTable {
  title: string
  columns: string[]
  rows: (string | number)[][]
}

/** `?from=&to=` for the API: only the dates that are set. */
export const reportQuery = (from: string, to: string): Record<string, string> => ({ ...(from ? { from } : {}), ...(to ? { to } : {}) })

/** Sales reports (`reports.view`): pick a report and a date range, read the table, download it as CSV. */
export function ReportsPage() {
  const [report, setReport] = useState<(typeof REPORTS)[number]['key']>('leads')
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const [busy, setBusy] = useState(false)
  const meta = REPORTS.find((r) => r.key === report)!
  const query = reportQuery(from, to)
  const invalid = Boolean(from && to && to < from)

  const table = useQuery({
    queryKey: ['report', report, from, to],
    enabled: !invalid,
    queryFn: async () => ((await api.request('GET', `/v1/reports/${report}`, { query })) as { data: ReportTable }).data,
  })

  // The export is a file, not JSON: fetch it with the token and hand the blob to the browser.
  const download = async () => {
    setBusy(true)
    try {
      const token = await getValidToken()
      const qs = new URLSearchParams(query).toString()
      const response = await fetch(`${config.apiBase()}/v1/reports/${report}/export${qs ? `?${qs}` : ''}`, { headers: { Authorization: `Bearer ${token}`, Accept: 'text/csv' } })
      if (!response.ok) throw new Error(String(response.status))

      const url = URL.createObjectURL(await response.blob())
      const link = document.createElement('a')
      link.href = url
      link.download = `${report}-report.csv`
      link.click()
      URL.revokeObjectURL(url)
    } catch {
      toast.error('Could not download the report')
    } finally {
      setBusy(false)
    }
  }

  return (
    <section className="space-y-4">
      <header className="flex flex-wrap items-center justify-between gap-2">
        <h1 className="text-2xl font-semibold">Reports</h1>
        <IconButton showText variant="primary" icon="upload" label="Download CSV" disabled={busy || invalid || !table.data} onClick={() => void download()} />
      </header>

      <form className="flex flex-wrap items-end gap-3" aria-label="Report options" onSubmit={(e) => e.preventDefault()}>
        <div>
          <label htmlFor="report" className="block text-sm text-ink-muted">
            Report
          </label>
          <select id="report" className="field" value={report} onChange={(e) => setReport(e.target.value as typeof report)}>
            {REPORTS.map((r) => (
              <option key={r.key} value={r.key}>
                {r.label}
              </option>
            ))}
          </select>
        </div>
        <div>
          <label htmlFor="report-from" className="block text-sm text-ink-muted">
            From
          </label>
          <input id="report-from" type="date" className="field" value={from} onChange={(e) => setFrom(e.target.value)} />
        </div>
        <div>
          <label htmlFor="report-to" className="block text-sm text-ink-muted">
            To
          </label>
          <input id="report-to" type="date" className="field" value={to} onChange={(e) => setTo(e.target.value)} />
        </div>
      </form>
      <p className="text-sm text-ink-muted">{meta.dates}</p>
      {invalid && (
        <p role="alert" className="text-danger">
          The end date is before the start date.
        </p>
      )}
      {table.isError && (
        <p role="alert" className="text-danger">
          Could not load the report.
        </p>
      )}

      {table.data && (
        <div className="overflow-x-auto rounded-md border border-line">
          <table className="w-full text-start text-sm">
            <caption className="sr-only">{table.data.title}</caption>
            <thead className="border-b border-line bg-canvas text-ink-muted">
              <tr>
                {table.data.columns.map((c) => (
                  <th key={c} scope="col" className="px-3 py-2 text-start font-semibold">
                    {c}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {table.data.rows.map((row) => (
                <tr key={String(row[0])} className={`border-b border-line last:border-0 ${row[0] === 'Total' ? 'font-semibold' : ''}`}>
                  {row.map((cell, i) => (
                    <td key={table.data.columns[i]} className={`px-3 py-2 ${i > 0 ? 'tabular-nums' : ''}`}>
                      {cell}
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  )
}
