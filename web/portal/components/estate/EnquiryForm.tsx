'use client'

import { useCallback, useEffect, useId, useState, type FormEvent } from 'react'
import type { Messages } from '../../lib/i18n'
import { btn, control } from './ui'

type Status = 'idle' | 'sending' | 'success' | 'error'

interface Captcha {
  id: string
  question: string
}

const empty = { name: '', email: '', phone: '', message: '', viewing: false, when: '', captcha_answer: '', website: '' }

/** `datetime-local` wants "YYYY-MM-DDTHH:mm" in the visitor's own time zone. */
const localInput = (d: Date) => new Date(d.getTime() - d.getTimezoneOffset() * 60_000).toISOString().slice(0, 16)

/**
 * Enquiry form for one property or project. With "I would like a viewing" ticked it also sends the preferred time, which
 * the API stores as a viewing request on the lead (an agent confirms it; nothing is booked here). Captcha and honeypot
 * work as on the contact form: a fresh challenge from /api/contact/captcha for every attempt, `website` is the honeypot.
 */
export function EnquiryForm({ messages, propertyId, projectId, subject }: { messages: Messages; propertyId?: number; projectId?: number; subject: string }) {
  const t = messages.estate.enquiry
  const c = messages.contactPage
  const uid = useId()
  const [values, setValues] = useState(empty)
  const [captcha, setCaptcha] = useState<Captcha | null>(null)
  const [status, setStatus] = useState<Status>('idle')
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [notice, setNotice] = useState('')
  const [earliest, setEarliest] = useState('')
  const [askedViewing, setAskedViewing] = useState(false)

  const loadCaptcha = useCallback(async () => {
    setCaptcha(null)
    try {
      const response = await fetch('/api/contact/captcha', { cache: 'no-store' })
      setCaptcha(((await response.json()) as { data: Captcha }).data)
    } catch {
      setCaptcha(null)
    }
  }, [])

  useEffect(() => {
    // Browser-only: a cached page must never ship a used-up challenge or a stale "earliest" time.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    void loadCaptcha()
    setEarliest(localInput(new Date(Date.now() + 3_600_000)))
  }, [loadCaptcha])

  const set = <K extends keyof typeof empty>(name: K, value: (typeof empty)[K]) => setValues((v) => ({ ...v, [name]: value }))

  async function submit(event: FormEvent) {
    event.preventDefault()
    if (!captcha) return

    if (!values.email.trim() && !values.phone.trim()) {
      setErrors({ email: t.contactNeeded })
      setNotice(c.errorFields)
      setStatus('error')

      return
    }
    if (values.viewing && !values.when) {
      setErrors({ viewing_requested_at: t.whenNeeded })
      setNotice(c.errorFields)
      setStatus('error')

      return
    }

    setStatus('sending')
    setErrors({})
    setNotice('')

    try {
      const response = await fetch('/api/leads', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({
          name: values.name,
          email: values.email || null,
          phone: values.phone || null,
          message: values.message || null,
          property_id: propertyId,
          project_id: projectId,
          viewing_requested_at: values.viewing && values.when ? new Date(values.when).toISOString() : null,
          captcha_id: captcha.id,
          captcha_answer: Number.parseInt(values.captcha_answer, 10),
          website: values.website || undefined,
        }),
      })

      if (response.ok) {
        setAskedViewing(values.viewing)
        setStatus('success')
        setValues(empty)

        return
      }

      const body = (await response.json().catch(() => ({}))) as { error?: { details?: Record<string, string[]> } }
      const details = body.error?.details ?? {}
      setErrors(Object.fromEntries(Object.entries(details).map(([field, list]) => [field, list[0] ?? ''])))
      setNotice(response.status === 429 ? c.errorThrottle : details.captcha_answer || details.captcha_id ? c.errorCaptcha : response.status === 422 ? c.errorFields : c.errorGeneric)
      setStatus('error')
    } catch {
      setNotice(c.errorGeneric)
      setStatus('error')
    }

    // The challenge is single-use whatever happened.
    setValues((v) => ({ ...v, captcha_answer: '' }))
    void loadCaptcha()
  }

  if (status === 'success') {
    return (
      <div role="status" className="space-y-3 rounded-card border border-success bg-surface p-5">
        <p className="font-semibold text-success">{askedViewing ? t.successViewing : t.success}</p>
        <button
          type="button"
          className="rounded-pill border border-line px-4 py-2 text-sm font-semibold hover:bg-brand-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600"
          onClick={() => {
            setStatus('idle')
            void loadCaptcha()
          }}
        >
          {c.another}
        </button>
      </div>
    )
  }

  const id = (name: string) => `${uid}-${name}`
  const text = (name: 'name' | 'email' | 'phone', label: string, type: string, autoComplete: string, required = false) => (
    <div>
      <label htmlFor={id(name)} className="mb-1 block text-sm font-medium text-ink-muted">
        {label}
      </label>
      <input
        id={id(name)}
        name={name}
        type={type}
        autoComplete={autoComplete}
        required={required}
        aria-invalid={errors[name] ? true : undefined}
        aria-describedby={errors[name] ? id(`${name}-error`) : undefined}
        className={control}
        value={values[name]}
        onChange={(e) => set(name, e.target.value)}
      />
      {errors[name] && (
        <p id={id(`${name}-error`)} className="mt-1 text-sm text-danger">
          {errors[name]}
        </p>
      )}
    </div>
  )

  return (
    <form onSubmit={submit} noValidate aria-label={t.title.replace('{subject}', subject)} className="space-y-4">
      {notice && (
        <p role="alert" className="rounded-md border border-danger bg-surface p-3 text-danger">
          {notice}
        </p>
      )}

      {text('name', c.name, 'text', 'name', true)}
      {text('email', c.email, 'email', 'email')}
      {text('phone', c.phone, 'tel', 'tel')}
      <p className="-mt-2 text-sm text-ink-muted">{t.contactHint}</p>

      <div>
        <label htmlFor={id('message')} className="mb-1 block text-sm font-medium text-ink-muted">
          {c.message}
        </label>
        <textarea
          id={id('message')}
          name="message"
          rows={4}
          aria-invalid={errors.message ? true : undefined}
          aria-describedby={errors.message ? id('message-error') : undefined}
          className={control}
          value={values.message}
          onChange={(e) => set('message', e.target.value)}
        />
        {errors.message && (
          <p id={id('message-error')} className="mt-1 text-sm text-danger">
            {errors.message}
          </p>
        )}
      </div>

      <div>
        <label className="flex cursor-pointer items-center gap-2 font-medium">
          <input type="checkbox" checked={values.viewing} onChange={(e) => set('viewing', e.target.checked)} className="size-4 accent-brand-700" />
          {t.wantViewing}
        </label>
        {values.viewing && (
          <div className="mt-3">
            <label htmlFor={id('when')} className="mb-1 block text-sm font-medium text-ink-muted">
              {t.when}
            </label>
            <input
              id={id('when')}
              name="viewing_requested_at"
              type="datetime-local"
              min={earliest}
              required
              aria-invalid={errors.viewing_requested_at ? true : undefined}
              aria-describedby={id('when-hint')}
              className={control}
              value={values.when}
              onChange={(e) => set('when', e.target.value)}
            />
            <p id={id('when-hint')} className={`mt-1 text-sm ${errors.viewing_requested_at ? 'text-danger' : 'text-ink-muted'}`}>
              {errors.viewing_requested_at || t.whenHint}
            </p>
          </div>
        )}
      </div>

      {/* Honeypot: off-screen (not display:none, which some bots skip), not focusable, not announced. */}
      <div aria-hidden="true" className="absolute -start-[9999px] h-0 w-0 overflow-hidden">
        <label htmlFor={id('website')}>Website</label>
        <input id={id('website')} name="website" type="text" tabIndex={-1} autoComplete="off" value={values.website} onChange={(e) => set('website', e.target.value)} />
      </div>

      <div>
        <label htmlFor={id('captcha')} className="mb-1 block text-sm font-medium text-ink-muted">
          {captcha ? c.captcha.replace('{question}', captcha.question) : c.captchaLoading}
        </label>
        <input
          id={id('captcha')}
          name="captcha_answer"
          type="text"
          inputMode="numeric"
          autoComplete="off"
          required
          disabled={!captcha}
          aria-invalid={errors.captcha_answer ? true : undefined}
          className={`${control} max-w-40`}
          value={values.captcha_answer}
          onChange={(e) => set('captcha_answer', e.target.value)}
        />
      </div>

      <button type="submit" disabled={status === 'sending' || !captcha} className={`${btn} disabled:opacity-60`}>
        {status === 'sending' ? c.sending : values.viewing ? t.sendViewing : t.send}
      </button>
    </form>
  )
}
