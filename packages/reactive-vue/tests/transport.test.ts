import { describe, expect, it, vi } from 'vitest'

import { ValidationError } from '../src/errors'
import { createHttpTransport, type HttpPoster } from '../src/transport'

describe('createHttpTransport', () => {
  it('posts to the four endpoints with the documented bodies', async () => {
    const post = vi.fn<(path: string, body?: unknown) => Promise<unknown>>(async () => ({
      subscriptionId: 's',
      result: [],
      mutationId: 3,
    }))
    const transport = createHttpTransport({ post: post as HttpPoster['post'] })

    await transport.subscribe('notes.list', { ownerId: 'u' })
    await transport.unsubscribe('s')
    await transport.mutate('notes.create', { title: 'x' })
    await transport.query('notes.list', {})

    expect(post.mock.calls.map((call) => call[0])).toEqual([
      '/rq/subscribe',
      '/rq/unsubscribe',
      '/rq/mutate',
      '/rq/query',
    ])
    expect(post.mock.calls[0]?.[1]).toEqual({ query: 'notes.list', args: { ownerId: 'u' } })
    expect(post.mock.calls[2]?.[1]).toEqual({ mutation: 'notes.create', args: { title: 'x' } })
  })

  it('turns a 422 into a ValidationError with field errors', async () => {
    const error = Object.assign(new Error('The title field is required.'), {
      status: 422,
      data: {
        message: 'The title field is required.',
        errors: { title: ['The title field is required.'] },
      },
    })
    const transport = createHttpTransport({ post: vi.fn(async () => Promise.reject(error)) })

    await expect(transport.mutate('notes.create', {})).rejects.toBeInstanceOf(ValidationError)
    await transport.mutate('notes.create', {}).catch((e: ValidationError) => {
      expect(e.first('title')).toBe('The title field is required.')
    })
  })

  it('passes other errors through unchanged', async () => {
    const error = Object.assign(new Error('Forbidden'), { status: 403 })
    const transport = createHttpTransport({ post: vi.fn(async () => Promise.reject(error)) })

    await expect(transport.query('x', {})).rejects.toBe(error)
  })
})
