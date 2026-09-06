import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'

import { UserAvatar, type PresenceStatus } from '~/components/ui/avatar'

/** The token each state has to reach for; a raw Tailwind colour is a defect. */
const tokens: Record<PresenceStatus, string> = {
  online: 'bg-success',
  offline: 'bg-input',
  unavailable: 'bg-destructive',
}

const dot = (status: PresenceStatus | null) => {
  const wrapper = mount(UserAvatar, { props: { name: 'Ada Lovelace', status } })
  const indicator = wrapper.find('.sr-only')

  return { wrapper, html: wrapper.html(), announced: indicator.exists() ? indicator.text() : null }
}

describe('UserAvatar', () => {
  it('shows no indicator until one is asked for', () => {
    expect(dot(null).announced).toBeNull()
    expect(
      mount(UserAvatar, { props: { name: 'Ada' } })
        .find('.sr-only')
        .exists()
    ).toBe(false)
  })

  it('draws one token per state and announces it in words', () => {
    const states: Array<[PresenceStatus, string]> = [
      ['online', 'Ada Lovelace is online'],
      ['offline', 'Ada Lovelace is offline'],
      ['unavailable', 'Ada Lovelace is unavailable'],
    ]

    for (const [status, announced] of states) {
      const { html, announced: text } = dot(status)

      expect(text).toBe(announced)
      expect(html).toContain(tokens[status])
    }
  })

  it('lets the caller say what the state means', () => {
    const wrapper = mount(UserAvatar, {
      props: { name: 'Ada', status: 'unavailable', statusLabel: 'Ada is in a meeting' },
    })

    expect(wrapper.find('.sr-only').text()).toBe('Ada is in a meeting')
  })
})
