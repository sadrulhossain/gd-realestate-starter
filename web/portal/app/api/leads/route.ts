const apiBase = () => (process.env.API_INTERNAL_URL ?? 'http://api.localhost/api').replace(/\/+$/, '')

/**
 * Browser enquiry form -> API `POST /v1/public/leads`. Same contract as /api/contact: the JSON is forwarded as is
 * (captcha id/answer and the honeypot included) and the API's status and body come back, so 422 field errors and 429
 * throttling reach the form.
 */
export async function POST(request: Request) {
  const body = await request.text()
  const forwardedFor = request.headers.get('x-forwarded-for')

  try {
    const response = await fetch(`${apiBase()}/v1/public/leads`, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        ...(forwardedFor ? { 'X-Forwarded-For': forwardedFor } : {}),
      },
      body,
      cache: 'no-store',
    })

    return new Response(await response.text(), { status: response.status, headers: { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' } })
  } catch {
    return Response.json({ error: { code: 'unavailable', message: 'The service is unavailable.' } }, { status: 502 })
  }
}
