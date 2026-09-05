import { describe, expect, it, vi } from 'vitest'

import { consumeSseStream, parseSseEvent, parseStreamErrorResponse } from '~/lib/sse'

/** A body reader that hands out the given chunks, split wherever the test says. */
const readerOf = (...chunks: string[]): ReadableStreamDefaultReader<Uint8Array> => {
  const encoder = new TextEncoder()
  return new ReadableStream<Uint8Array>({
    start(controller) {
      for (const chunk of chunks) controller.enqueue(encoder.encode(chunk))
      controller.close()
    },
  }).getReader()
}

const frame = (payload: Record<string, unknown>): string => `data: ${JSON.stringify(payload)}\n\n`

describe('parseSseEvent', () => {
  it('takes an object with a type and rejects everything else', () => {
    expect(parseSseEvent(`data: {"type":"delta","content":"hi"}`)).toEqual({
      type: 'delta',
      content: 'hi',
    })
    expect(parseSseEvent(': ping')).toBeNull()
    expect(parseSseEvent('data: [1,2]')).toBeNull()
    expect(parseSseEvent('data: not json')).toBeNull()
  })
})

describe('consumeSseStream', () => {
  it('reassembles a frame split across chunks', async () => {
    const onDelta = vi.fn()
    const onDone = vi.fn()

    await consumeSseStream(
      readerOf(
        ': open\n\ndata: {"type":"del',
        `ta","content":"hi"}\n\n`,
        frame({ type: 'done', content: 'hi' })
      ),
      {
        onDelta,
        onDone,
      }
    )

    expect(onDelta).toHaveBeenCalledWith('hi')
    expect(onDone).toHaveBeenCalledWith('hi', undefined)
  })

  it('stops at the first terminal event so a result is never applied twice', async () => {
    const onDone = vi.fn()

    await consumeSseStream(
      readerOf(
        frame({ type: 'done', content: 'first' }),
        frame({ type: 'done', content: 'second' })
      ),
      { onDone }
    )

    expect(onDone).toHaveBeenCalledTimes(1)
    expect(onDone).toHaveBeenCalledWith('first', undefined)
  })

  it('reports a stream that ends without a terminal event', async () => {
    const onError = vi.fn()

    await consumeSseStream(readerOf(frame({ type: 'delta', content: 'half' })), { onError })

    expect(onError).toHaveBeenCalledWith(expect.stringContaining('ended'), 'incomplete')
  })
})

describe('parseStreamErrorResponse', () => {
  it('keeps the server reason so the caller can branch on it', async () => {
    const response = new Response(
      JSON.stringify({ message: 'No AI here', reason: 'not_configured' }),
      {
        status: 503,
      }
    )

    await expect(parseStreamErrorResponse(response)).resolves.toEqual({
      message: 'No AI here',
      reason: 'not_configured',
    })
  })

  it('names an expired session on 419 without reading the body', async () => {
    const { reason } = await parseStreamErrorResponse(new Response('', { status: 419 }))

    expect(reason).toBe('csrf')
  })
})
