import { describe, expect, it, vi } from 'vitest'

import type { ApiClient } from '~/api/client'
import { PeopleResource } from '~/api/resources/people'

const resourceWithSpy = () => {
  const get = vi.fn().mockResolvedValue({ data: [], counts: { active: 0, pending: 0, total: 0 } })

  return { resource: new PeopleResource({ get } as unknown as ApiClient), get }
}

describe('PeopleResource', () => {
  it('leaves out the filters that mean "no filter"', async () => {
    const { resource, get } = resourceWithSpy()

    await resource.index({ search: '', role: '', status: 'all', page: 2 })

    expect(get).toHaveBeenCalledWith('/api/people', {
      page: 2,
      per_page: undefined,
      search: undefined,
      role: undefined,
      // `all` is the absence of a segment, not a value the server has to parse.
      status: undefined,
      sort: undefined,
      direction: undefined,
    })
  })

  it('passes the segment and role through when they narrow the list', async () => {
    const { resource, get } = resourceWithSpy()

    await resource.index({ status: 'pending', role: 'admin', search: 'ada', sort: 'created_at' })

    expect(get).toHaveBeenCalledWith(
      '/api/people',
      expect.objectContaining({
        status: 'pending',
        role: 'admin',
        search: 'ada',
        sort: 'created_at',
      })
    )
  })
})
