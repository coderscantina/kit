import { describe, expect, it, vi } from 'vitest'

import type { ApiClient } from '~/api/client'
import { PeopleResource } from '~/api/resources/people'

const resourceWithSpy = () => {
  const get = vi.fn().mockResolvedValue({ data: [], counts: { active: 0, pending: 0, total: 0 } })

  return { resource: new PeopleResource({ get } as unknown as ApiClient), get }
}

describe('PeopleResource', () => {
  it('passes the table query bag through untouched', async () => {
    const { resource, get } = resourceWithSpy()

    // Dropping the parameters that mean "no filter" is the table state's job,
    // upstream of here; the resource must not second-guess what it is handed.
    await resource.index({ page: 2, per_page: 50, sort: '-created_at', q: 'ada', role: 'admin' })

    expect(get).toHaveBeenCalledWith('/api/people', {
      page: 2,
      per_page: 50,
      sort: '-created_at',
      q: 'ada',
      role: 'admin',
    })
  })
})
