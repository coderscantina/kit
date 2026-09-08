import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'

import NotificationItem from '~/components/notifications/NotificationItem.vue'

const stub = { template: '<div />' }

const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/', name: 'dashboard', component: stub },
    { path: '/users', name: 'users', component: stub },
    { path: '/account/security', name: 'account-security', component: stub },
  ],
})

const notification = (
  overrides: Partial<App.Data.NotificationData> = {}
): App.Data.NotificationData => ({
  id: 'n1',
  type: 'people.invite_accepted',
  data: { name: 'Ada', email: 'ada@example.test' },
  status: 'unseen',
  deliveries: { database: '2026-09-07T10:00:00+00:00', mail: '2026-09-07T10:05:00+00:00' },
  seenAt: null,
  archivedAt: null,
  createdAt: '2026-09-07T10:00:00+00:00',
  ...overrides,
})

const render = (value: App.Data.NotificationData) =>
  mount(NotificationItem, {
    props: { notification: value },
    global: { plugins: [router] },
  })

describe('NotificationItem', () => {
  it('renders the copy for the type with the payload as parameters', async () => {
    await router.push('/')
    const wrapper = render(notification())

    expect(wrapper.text()).toContain('Invitation accepted')
    expect(wrapper.text()).toContain('Ada (ada@example.test) accepted your invitation.')
  })

  it('falls back to the raw type rather than a blank row', async () => {
    await router.push('/')
    const wrapper = render(notification({ type: 'something.retired' }))

    expect(wrapper.text()).toContain('something.retired')
  })

  it('offers restore instead of archive once a notification is archived', async () => {
    await router.push('/')

    const active = render(notification())
    expect(active.find('button[aria-label="Archive"]').exists()).toBe(true)

    const archived = render(
      notification({ status: 'archived', archivedAt: '2026-09-07T11:00:00+00:00' })
    )
    expect(archived.find('button[aria-label="Put back"]').exists()).toBe(true)
  })

  it('archiving does not also open the notification', async () => {
    await router.push('/')
    const wrapper = render(notification())

    await wrapper.find('button[aria-label="Archive"]').trigger('click')

    expect(wrapper.emitted('archive')).toHaveLength(1)
    expect(wrapper.emitted('open')).toBeUndefined()
  })
})
