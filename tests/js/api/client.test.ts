import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import { ApiClient } from '~/api/client'

const json = (body: unknown, status = 200) =>
  new Response(JSON.stringify(body), { status, headers: { 'content-type': 'application/json' } })

let fetchMock: ReturnType<typeof vi.fn>

beforeEach(() => {
  fetchMock = vi.fn()
  vi.stubGlobal('fetch', fetchMock)
  document.cookie = 'XSRF-TOKEN=tok%3D; path=/'
})

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('resolveUrl', () => {
  it('serializes query params the way Laravel parses them', () => {
    const client = new ApiClient({ baseURL: '' })

    const url = client.resolveUrl('/api/things', {
      filter: { parent_id: 'x', archived: false },
      tags: ['a', 'b'],
      since: new Date('2026-01-02T03:04:05.000Z'),
      skip: undefined,
      none: null,
    })

    expect(decodeURIComponent(url)).toBe(
      '/api/things?filter[parent_id]=x&filter[archived]=false&tags[]=a&tags[]=b&since=2026-01-02T03:04:05.000Z'
    )
  })
})

describe('request', () => {
  it('sends the XSRF header and retries a write exactly once on 419', async () => {
    fetchMock
      .mockImplementationOnce(async () => new Response(null, { status: 204 }))
      .mockImplementationOnce(async () => json({ message: 'CSRF token mismatch.' }, 419))
      .mockImplementationOnce(async () => new Response(null, { status: 204 }))
      .mockImplementationOnce(async () => json({ ok: true }))

    const client = new ApiClient()
    const result = await client.post<{ ok: boolean }>('/api/x', { a: 1 })

    expect(result).toEqual({ ok: true })
    // cookie prime, write, cookie refresh, retried write; never a second retry
    expect(fetchMock).toHaveBeenCalledTimes(4)
    expect(fetchMock.mock.calls[2]?.[0]).toBe('/auth/csrf-cookie')
    const retried = fetchMock.mock.calls[3]?.[1] as RequestInit | undefined
    const headers = (retried?.headers ?? {}) as Record<string, string>
    expect(headers['X-XSRF-TOKEN']).toBe('tok=')
  })

  it('hands a 401 to the auth handler and does not retry unless told to', async () => {
    fetchMock.mockImplementation(async () => json({ message: 'Unauthenticated.' }, 401))
    const handleUnauthorized = vi.fn(async () => undefined)
    const client = new ApiClient()
    client.setAuthHandler({ handleUnauthorized })

    await expect(client.get('/auth/me')).rejects.toMatchObject({ status: 401 })
    expect(handleUnauthorized).toHaveBeenCalledWith('/auth/me', expect.anything())
    expect(fetchMock).toHaveBeenCalledTimes(1)
  })

  it('truncates a non-JSON error body to 500 characters and keeps the status', async () => {
    fetchMock.mockImplementation(
      async () =>
        new Response('<html>'.padEnd(2000, 'x'), {
          status: 502,
          headers: { 'content-type': 'text/html' },
        })
    )
    const client = new ApiClient()

    const error = (await client.get('/api/x').catch((e: unknown) => e)) as Error & {
      status: number
    }

    expect(error.status).toBe(502)
    expect(error.message).toHaveLength(500)
  })

  it('returns undefined for 204 and notifies observers of every response', async () => {
    fetchMock.mockImplementation(
      async () => new Response(null, { status: 204, headers: { 'x-app-version': '9' } })
    )
    const client = new ApiClient()
    const seen: string[] = []
    client.observe((response) => seen.push(response.headers.get('x-app-version') ?? ''))

    await expect(client.delete('/api/x')).resolves.toBeUndefined()
    expect(seen).toEqual(['9'])
  })
})

describe('upload', () => {
  /** Answers each send with the next queued status, after one progress tick. */
  class FakeXhr {
    static answers: Array<{ status: number; body: string }> = []
    static sent: Array<{ headers: Record<string, string> }> = []

    status = 0
    responseText = ''
    withCredentials = false
    upload: { onprogress: ((event: ProgressEvent) => void) | null } = { onprogress: null }
    onload: (() => void) | null = null
    onerror: (() => void) | null = null
    onabort: (() => void) | null = null
    private headers: Record<string, string> = {}

    open(): void {}
    abort(): void {}
    setRequestHeader(name: string, value: string): void {
      this.headers[name] = value
    }
    getResponseHeader(): string {
      return 'application/json'
    }
    send(): void {
      FakeXhr.sent.push({ headers: this.headers })
      const answer = FakeXhr.answers.shift() ?? { status: 500, body: '{}' }
      this.upload.onprogress?.({ lengthComputable: true, loaded: 5, total: 10 } as ProgressEvent)
      this.status = answer.status
      this.responseText = answer.body
      this.onload?.()
    }
  }

  beforeEach(() => {
    FakeXhr.answers = []
    FakeXhr.sent = []
    vi.stubGlobal('XMLHttpRequest', FakeXhr)
    fetchMock.mockImplementation(async () => new Response(null, { status: 204 }))
  })

  it('reports progress, sends the XSRF header and retries once on 419', async () => {
    FakeXhr.answers = [
      { status: 419, body: '{"message":"CSRF token mismatch."}' },
      { status: 201, body: '{"id":"a1"}' },
    ]
    const progress: number[] = []

    const result = await new ApiClient().upload<{ id: string }>(
      '/api/attachments',
      new FormData(),
      {
        onProgress: (fraction) => progress.push(fraction),
      }
    )

    expect(result).toEqual({ id: 'a1' })
    expect(progress).toEqual([0.5, 0.5])
    expect(FakeXhr.sent).toHaveLength(2)
    expect(FakeXhr.sent[0]?.headers['X-XSRF-TOKEN']).toBe('tok=')
  })

  it('turns a 422 into the ApiError every other call throws', async () => {
    FakeXhr.answers = [
      { status: 422, body: '{"message":"Too big.","errors":{"file":["Too big."]}}' },
    ]

    await expect(new ApiClient().upload('/api/attachments', new FormData())).rejects.toMatchObject({
      status: 422,
      message: 'Too big.',
    })
  })
})
