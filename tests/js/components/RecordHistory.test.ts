import { afterEach, describe, expect, it } from 'vitest'
import { defineComponent, h } from 'vue'

import RecordHistory from '~/components/records/RecordHistory.vue'

import { mountWithQuery } from '../support/harness'

const entry = (overrides: Partial<App.Data.AuditEntryData>): App.Data.AuditEntryData => ({
  id: '01',
  event: 'updated',
  actorName: 'Ada Lovelace',
  actorAvatarUrl: null,
  impersonatorName: null,
  changes: [],
  createdAt: new Date().toISOString(),
  ...overrides,
})

const mountHistory = (entries: App.Data.AuditEntryData[]) =>
  mountWithQuery(
    defineComponent(() => () => h(RecordHistory, { type: 'users', id: 'u1' })),
    { seed: [[['rq', 'audit.history', { type: 'users', id: 'u1' }], entries]] }
  )

let unmount: (() => void) | undefined

afterEach(() => {
  unmount?.()
  unmount = undefined
})

describe('RecordHistory', () => {
  it('shows who changed which field from what to what', () => {
    const { wrapper } = mountHistory([
      entry({
        changes: [{ field: 'name', before: 'Ada', after: 'Grace', redacted: false }],
      }),
    ])
    unmount = () => wrapper.unmount()

    expect(wrapper.text()).toContain('Ada Lovelace changed')
    expect(wrapper.find('del').text()).toBe('Ada')
    expect(wrapper.find('dd').text()).toContain('Grace')
  })

  it('never shows a redacted value, and names the system when nobody acted', () => {
    const { wrapper } = mountHistory([
      entry({
        actorName: null,
        changes: [{ field: 'password', before: null, after: null, redacted: true }],
      }),
    ])
    unmount = () => wrapper.unmount()

    expect(wrapper.text()).toContain('The system changed')
    expect(wrapper.text()).toContain('changed, value hidden')
  })

  it('keeps both people under impersonation', () => {
    const { wrapper } = mountHistory([entry({ impersonatorName: 'Root' })])
    unmount = () => wrapper.unmount()

    expect(wrapper.text()).toContain('Ada Lovelace (by Root) changed')
  })

  it('says so when nothing has happened yet', () => {
    const { wrapper } = mountHistory([])
    unmount = () => wrapper.unmount()

    expect(wrapper.text()).toContain('Nothing has changed here yet.')
  })
})
