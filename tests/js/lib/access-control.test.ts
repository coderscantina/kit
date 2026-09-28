import { describe, expect, it } from 'vitest'

import {
  accountNavigationItems,
  canAccessRouteByName,
  filterNavigationItems,
  firstAllowedRoute,
  hasAbilityRequirement,
  navigationItems,
} from '~/lib/access-control'

const me = (abilities: string[], isRoot = false): App.Data.MeData => ({
  user: {
    id: 'u',
    name: 'Mike',
    email: 'm@example.test',
    locale: 'en',
    avatarUrl: null,
    pendingEmail: null,
    role: 'member',
    isRoot,
    emailVerified: true,
    twoFactorEnabled: false,
    lastLoginAt: null,
    createdAt: '',
  },
  abilities,
  impersonating: false,
})

describe('hasAbilityRequirement', () => {
  it('handles string, anyOf and allOf requirements', () => {
    const context = me(['app.access', 'users.view'])

    expect(hasAbilityRequirement(context, 'app.access')).toBe(true)
    expect(hasAbilityRequirement(context, 'users.manage')).toBe(false)
    expect(hasAbilityRequirement(context, { anyOf: ['users.manage', 'users.view'] })).toBe(true)
    expect(hasAbilityRequirement(context, { allOf: ['users.manage', 'users.view'] })).toBe(false)
    expect(hasAbilityRequirement(context, {})).toBe(false)
    expect(hasAbilityRequirement(null, 'app.access')).toBe(false)
  })

  it('root short-circuits everything', () => {
    expect(hasAbilityRequirement(me([], true), 'anything')).toBe(true)
  })
})

describe('navigation', () => {
  it('hides pages the user cannot open and picks the first allowed one as fallback', () => {
    const context = { me: me(['app.access']) }

    expect(filterNavigationItems(navigationItems, context).map((item) => item.routeName)).toEqual([
      'dashboard',
    ])
    expect(
      filterNavigationItems(accountNavigationItems, context).map((item) => item.routeName)
    ).toEqual([
      'account-profile',
      'account-security',
      'account-notifications',
      'account-data',
      'account-api',
    ])
    expect(canAccessRouteByName('users', context)).toBe(false)
    expect(firstAllowedRoute(context)).toEqual({ name: 'dashboard' })
    expect(firstAllowedRoute({ me: me([]) })).toBeNull()
  })

  it('routes without a requirement are open', () => {
    expect(canAccessRouteByName('login', { me: null })).toBe(true)
  })
})
