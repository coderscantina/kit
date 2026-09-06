import { afterEach, describe, expect, it } from 'vitest'

import { useAuth } from '~/composables/useAuth'
import { useNavigation } from '~/composables/useNavigation'
import { navigationItems } from '~/lib/access-control'

import { withSetup, type Harness } from '../support/harness'

const root: App.Data.MeData = {
  user: {
    id: 'u',
    name: 'Mike',
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

const signIn = (me: App.Data.MeData | null) => {
  const auth = useAuth()
  auth.me.value = me
  auth.ready.value = true
}

afterEach(() => {
  signIn(null)
})

describe('useNavigation', () => {
  it('groups the items the way app.config asks for, and drops empty groups', () => {
    signIn(root)

    const { result, unmount }: Harness<ReturnType<typeof useNavigation>> = withSetup(useNavigation)

    const labels = result.sections.value.map((section) => section.labelKey)
    expect(labels).toEqual([null, 'nav.sections.manage'])

    const first = result.sections.value[0]
    expect(first?.items.map((item) => item.routeName)).toEqual(['dashboard'])

    unmount()
  })

  it('puts an item the config never mentions in the overflow section', () => {
    signIn(root)
    // What `make:feature` appends at `// kit:nav`: no config edit, still visible.
    navigationItems.push({ labelKey: 'nav.dashboard', icon: 'box', routeName: 'reports' })

    try {
      const { result, unmount }: Harness<ReturnType<typeof useNavigation>> =
        withSetup(useNavigation)

      const overflow = result.sections.value.find(
        (section) => section.labelKey === 'nav.sections.manage'
      )

      expect(overflow?.items.at(-1)?.routeName).toBe('reports')

      unmount()
    } finally {
      navigationItems.pop()
    }
  })

  it('shows nothing to a signed-out visitor', () => {
    signIn(null)

    const { result, unmount }: Harness<ReturnType<typeof useNavigation>> = withSetup(useNavigation)

    expect(result.items.value).toEqual([])
    expect(result.sections.value).toEqual([])

    unmount()
  })
})
