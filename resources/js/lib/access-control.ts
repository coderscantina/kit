import type { RouteLocationRaw } from 'vue-router'

import { runtimeConfig } from '~/lib/runtime-config'

export type AppRouteName = string

export interface AccessEvaluationContext {
  me?: App.Data.MeData | null
  routeName?: AppRouteName | null
}

export interface AbilityRequirement {
  anyOf?: string[]
  allOf?: string[]
}

export interface RouteAccessRequirement {
  abilities?: string | AbilityRequirement
  check?: (context: AccessEvaluationContext) => boolean
}

export interface NavigationItem {
  labelKey: string
  icon: string
  routeName: AppRouteName
}

/**
 * Sidebar order doubles as the access-denied fallback: the first item the
 * user may open is where a denial sends them.
 */
export const navigationItems: NavigationItem[] = [
  { labelKey: 'nav.dashboard', icon: 'home', routeName: 'dashboard' },
  { labelKey: 'nav.users', icon: 'users', routeName: 'users' },
  { labelKey: 'nav.profile', icon: 'user', routeName: 'account-profile' },
  { labelKey: 'nav.security', icon: 'shield', routeName: 'account-security' },
  // kit:nav
]

/** Route name → what it takes to open it. Drives the guard and the sidebar. */
export const routeAccessRequirements: Record<AppRouteName, RouteAccessRequirement> = {
  dashboard: { abilities: 'app.access' },
  users: { abilities: { anyOf: ['users.view', 'invites.view'] } },
  'account-profile': { abilities: 'app.access' },
  'account-security': { abilities: 'app.access' },
  impersonate: {
    check: ({ me }) => Boolean(me?.user.isRoot) && runtimeConfig.features.impersonation,
  },
  // kit:access
}

export const getRouteAccessRequirement = (
  routeName?: AppRouteName | null
): RouteAccessRequirement | null =>
  routeName ? (routeAccessRequirements[routeName] ?? null) : null

export function hasAbilityRequirement(
  me: App.Data.MeData | null | undefined,
  requirement?: string | AbilityRequirement
): boolean {
  if (!requirement) return true
  if (me?.user.isRoot) return true

  const abilities = new Set(me?.abilities ?? [])

  if (typeof requirement === 'string') return abilities.has(requirement)

  const anyOf = requirement.anyOf ?? []
  const allOf = requirement.allOf ?? []

  if (anyOf.length > 0 && !anyOf.some((ability) => abilities.has(ability))) return false
  if (allOf.length > 0 && !allOf.every((ability) => abilities.has(ability))) return false

  return anyOf.length > 0 || allOf.length > 0
}

export function canAccessRequirement(
  requirement: RouteAccessRequirement | null | undefined,
  context: AccessEvaluationContext
): boolean {
  if (!requirement) return true
  if (context.me?.user.isRoot) return true
  if (requirement.abilities && !hasAbilityRequirement(context.me, requirement.abilities))
    return false
  if (requirement.check) return requirement.check(context)
  return true
}

export const canAccessRouteByName = (
  routeName: AppRouteName,
  context: AccessEvaluationContext
): boolean => canAccessRequirement(getRouteAccessRequirement(routeName), { ...context, routeName })

export const filterNavigationItems = (
  items: NavigationItem[],
  context: AccessEvaluationContext
): NavigationItem[] => items.filter((item) => canAccessRouteByName(item.routeName, context))

export function firstAllowedRoute(context: AccessEvaluationContext): RouteLocationRaw | null {
  const item = navigationItems.find((entry) => canAccessRouteByName(entry.routeName, context))
  return item ? { name: item.routeName } : null
}
