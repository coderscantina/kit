import { describe, expect, it, vi } from 'vitest'

import { useAiStream } from '~/composables/useAiStream'

vi.mock('~/api', () => ({
  api: {
    client: {
      ensureCsrfCookie: vi.fn().mockResolvedValue(undefined),
      resolveUrl: (endpoint: string) => endpoint,
    },
  },
}))

const frame = (payload: Record<string, unknown>): string => `data: ${JSON.stringify(payload)}\n\n`

const streamResponse = (...frames: string[]): Response =>
  new Response(
    new ReadableStream<Uint8Array>({
      start(controller) {
        const encoder = new TextEncoder()
        for (const chunk of frames) controller.enqueue(encoder.encode(chunk))
        controller.close()
      },
    }),
    { status: 200 }
  )

const withXsrfCookie = (): void => {
  document.cookie = 'XSRF-TOKEN=token'
}

describe('useAiStream', () => {
  it('accumulates deltas and settles on the full answer', async () => {
    withXsrfCookie()
    vi.stubGlobal(
      'fetch',
      vi
        .fn()
        .mockResolvedValue(
          streamResponse(
            frame({ type: 'status', message: 'Thinking' }),
            frame({ type: 'delta', content: 'Hel' }),
            frame({ type: 'delta', content: 'lo' }),
            frame({ type: 'done', content: 'Hello' })
          )
        )
    )

    const assistant = useAiStream('assistant.ask')
    const answer = await assistant.run({ question: 'hi' })

    expect(answer).toBe('Hello')
    expect(assistant.content.value).toBe('Hello')
    // Status is progress, not result: it clears when the answer lands.
    expect(assistant.status.value).toBe('')
    expect(assistant.isStreaming.value).toBe(false)
    expect(assistant.error.value).toBeNull()
  })

  it('surfaces a pre-stream failure with its reason and never opens a stream', async () => {
    withXsrfCookie()
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue(
        new Response(JSON.stringify({ message: 'Not allowed', reason: 'plan_excluded' }), {
          status: 403,
        })
      )
    )

    const assistant = useAiStream('assistant.ask')
    await assistant.run({ question: 'hi' })

    expect(assistant.error.value).toBe('Not allowed')
    expect(assistant.reason.value).toBe('plan_excluded')
    expect(assistant.content.value).toBe('')
  })

  it('turns an error event into an error rather than an answer', async () => {
    withXsrfCookie()
    vi.stubGlobal(
      'fetch',
      vi
        .fn()
        .mockResolvedValue(
          streamResponse(
            frame({ type: 'error', message: 'Provider down', reason: 'provider_error' })
          )
        )
    )

    const assistant = useAiStream('assistant.ask')
    await assistant.run({ question: 'hi' })

    expect(assistant.error.value).toBe('Provider down')
    expect(assistant.reason.value).toBe('provider_error')
  })

  it('fails closed when the CSRF cookie is missing', async () => {
    document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT'
    const fetchMock = vi.fn()
    vi.stubGlobal('fetch', fetchMock)

    const assistant = useAiStream('assistant.ask')
    await assistant.run({ question: 'hi' })

    expect(fetchMock).not.toHaveBeenCalled()
    expect(assistant.reason.value).toBe('csrf')
  })
})
