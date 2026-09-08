import { VueQueryPlugin } from '@tanstack/vue-query'
import { mount } from '@vue/test-utils'
import { afterEach, describe, expect, it } from 'vitest'
import { defineComponent, h } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'

import PageActions from '~/components/app/PageActions.vue'
import { useAuth } from '~/composables/useAuth'
import { useSidebar } from '~/composables/useSidebar'
import AppLayout from '~/layouts/AppLayout.vue'
import { accountNavigationItems, navigationItems } from '~/lib/access-control'

const stub = { template: '<div />' }

/**
 * Routed off the navigation lists rather than a hand-written array: the
 * sidebar renders every item in them, and a RouterLink to a name this router
 * does not know throws. A generated feature adds an item, so a fixed list
 * here would make `make:feature` fail a test that has nothing to do with it.
 */
const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    ...[...navigationItems, ...accountNavigationItems].map((item) => ({
      path: item.routeName === 'dashboard' ? '/' : `/${item.routeName.replaceAll('-', '/')}`,
      name: item.routeName,
      component: stub,
    })),
    { path: '/login', name: 'login', component: stub },
  ],
})

const root: App.Data.MeData = {
  user: {
    id: 'u',
    name: 'Mike Wallner',
    email: 'm@example.test',
    locale: 'en',
    role: 'owner',
    isRoot: true,
    emailVerified: true,
    twoFactorEnabled: false,
    lastLoginAt: null,
    createdAt: '',
    avatarUrl: null,
    pendingEmail: null,
  },
  abilities: [],
  impersonating: false,
}

const mountShell = async () => {
  const auth = useAuth()
  auth.me.value = root
  auth.ready.value = true

  await router.push('/')
  await router.isReady()

  return mount(
    defineComponent({
      setup: () => () => h(AppLayout, null, { default: () => h('p', 'page body') }),
    }),
    // The header carries the notification bell, which is a reactive query,
    // so the shell needs a query client the way the real app has one.
    { global: { plugins: [router, VueQueryPlugin] } }
  )
}

afterEach(() => {
  const auth = useAuth()
  auth.me.value = null
  auth.ready.value = false
})

describe('the app shell', () => {
  it('renders the landmarks a keyboard user navigates by', async () => {
    const wrapper = await mountShell()

    expect(wrapper.find('a[href="#main-content"]').exists()).toBe(true)
    expect(wrapper.find('main#main-content').attributes('tabindex')).toBe('-1')
    expect(wrapper.find('nav[aria-label="Main navigation"]').exists()).toBe(true)
    expect(wrapper.find('header').exists()).toBe(true)
    expect(wrapper.find('[aria-live="polite"]').exists()).toBe(true)

    wrapper.unmount()
  })

  it('marks the current page on its nav link', async () => {
    const wrapper = await mountShell()

    const current = wrapper.find('nav a[aria-current="page"]')
    expect(current.exists()).toBe(true)
    expect(current.text()).toContain('Dashboard')

    wrapper.unmount()
  })

  it('renders the page inside the shell, not instead of it', async () => {
    const wrapper = await mountShell()

    expect(wrapper.find('main#main-content').text()).toContain('page body')

    wrapper.unmount()
  })

  it('teleports a page action into the header', async () => {
    const auth = useAuth()
    auth.me.value = root
    auth.ready.value = true

    await router.push('/')
    await router.isReady()

    const wrapper = mount(
      defineComponent({
        setup: () => () =>
          h(AppLayout, null, {
            default: () => h(PageActions, null, { default: () => h('button', 'New post') }),
          }),
      }),
      { global: { plugins: [router, VueQueryPlugin] }, attachTo: document.body }
    )

    await wrapper.vm.$nextTick()

    expect(document.querySelector('#app-header-actions')?.textContent).toBe('New post')

    wrapper.unmount()
  })

  it('keeps the account pages out of the sidebar and gives the phone a tab bar', async () => {
    const wrapper = await mountShell()

    const sidebarLinks = wrapper.findAll('aside#app-sidebar nav a').map((a) => a.attributes('href'))
    expect(sidebarLinks).not.toContain('/account/profile')

    const tabBar = wrapper.find('nav[aria-label="Quick navigation"]')
    expect(tabBar.exists()).toBe(true)
    expect(tabBar.find('a[aria-current="page"]').text()).toContain('Dashboard')
    expect(tabBar.find('button[aria-expanded]').text()).toContain('Menu')

    wrapper.unmount()
  })

  it('collapses the sidebar to the rail width', async () => {
    const wrapper = await mountShell()
    const sidebar = useSidebar()

    const aside = () => wrapper.find('aside#app-sidebar')
    expect(aside().attributes('style')).toContain(`${sidebar.width.value}px`)

    sidebar.collapsed.value = true
    await wrapper.vm.$nextTick()

    expect(aside().attributes('style')).toContain('56px')
    // The label survives as an accessible name even when it is not painted.
    expect(aside().find('nav a[aria-current="page"]').text()).toContain('Dashboard')

    sidebar.collapsed.value = false
    wrapper.unmount()
  })
})
