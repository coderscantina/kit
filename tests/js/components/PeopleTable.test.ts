import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'

import PeopleTable from '~/components/people/PeopleTable.vue'

const person = (overrides: Partial<App.Data.PersonData> = {}): App.Data.PersonData => ({
  kind: 'user',
  id: 'u1',
  name: 'Ada Lovelace',
  email: 'ada@example.test',
  role: 'admin',
  avatarUrl: null,
  state: 'active',
  isRoot: false,
  emailVerified: true,
  twoFactorEnabled: false,
  lastLoginAt: null,
  invitedBy: null,
  expiresAt: null,
  createdAt: '2026-01-01T00:00:00+00:00',
  canAssignRole: false,
  canRemove: false,
  canResend: false,
  ...overrides,
})

const roles: App.Data.RoleData[] = [
  { key: 'admin', name: 'Admin', level: 200, abilities: [] },
  { key: 'member', name: 'Member', level: 100, abilities: [] },
]

const table = (rows: App.Data.PersonData[]) =>
  mount(PeopleTable, {
    props: {
      rows,
      roles,
      loading: false,
      refetching: false,
      filtered: false,
      segment: 'all' as const,
      sort: { column: 'name', direction: 'asc' as const },
    },
  })

describe('PeopleTable', () => {
  it('shows an account by name and an invitation by address', () => {
    const wrapper = table([
      person(),
      person({ kind: 'invite', id: 'i1', name: null, email: 'bob@example.test', state: 'pending' }),
    ])

    const text = wrapper.text()

    expect(text).toContain('Ada Lovelace')
    // An invitation has no name, so the address stands in for one.
    expect(text).toContain('bob@example.test')
    // The role key is never shown raw; the role's name is.
    expect(text).toContain('Admin')
  })

  it('offers only the actions the server allowed on that row', async () => {
    const wrapper = table([
      person({ canRemove: false }),
      person({
        kind: 'invite',
        id: 'i1',
        name: null,
        email: 'bob@example.test',
        state: 'expired',
        canRemove: true,
        canResend: true,
      }),
    ])

    const buttons = wrapper.findAll('button[aria-label]')
    const labels = buttons.map((button) => button.attributes('aria-label'))

    // Two on the invitation, none on the account.
    expect(labels).toHaveLength(2)

    await buttons[0]?.trigger('click')
    expect(wrapper.emitted('resend')?.[0]?.[0]).toMatchObject({ id: 'i1' })

    await buttons[1]?.trigger('click')
    expect(wrapper.emitted('remove')?.[0]?.[0]).toMatchObject({ id: 'i1' })
  })

  it('turns the role badge into a picker only where the role may be changed', async () => {
    const wrapper = table([person({ canAssignRole: true })])

    const trigger = wrapper.find('button[aria-label="Change role"]')
    expect(trigger.exists()).toBe(true)

    await trigger.trigger('click')

    // The badge is gone once the cell is editing, so the two cannot both be live.
    expect(wrapper.find('button[aria-label="Change role"]').exists()).toBe(false)
  })
})
