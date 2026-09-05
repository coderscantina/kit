import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'

import { useAuth } from '~/composables/useAuth'
import { canAccessRouteByName, getRouteAccessRequirement } from '~/lib/access-control'

declare module 'vue-router' {
  interface RouteMeta {
    /** Resolved by app.vue: `app` (sidebar shell) or `unauthenticated`. */
    layout?: 'app' | 'unauthenticated'
    /** Only for signed-out visitors; signed-in users bounce to the dashboard. */
    guest?: boolean
    /** Reachable with or without a session. */
    public?: boolean
  }
}

const routes: RouteRecordRaw[] = [
  {
    path: '/',
    name: 'dashboard',
    component: () => import('~/pages/Dashboard.vue'),
    meta: { layout: 'app' },
  },
  {
    path: '/login',
    name: 'login',
    component: () => import('~/pages/auth/Login.vue'),
    meta: { layout: 'unauthenticated', guest: true },
  },
  {
    path: '/register',
    name: 'register',
    component: () => import('~/pages/auth/Register.vue'),
    meta: { layout: 'unauthenticated', guest: true },
  },
  {
    path: '/forgot-password',
    name: 'forgot-password',
    component: () => import('~/pages/auth/ForgotPassword.vue'),
    meta: { layout: 'unauthenticated', guest: true },
  },
  {
    path: '/reset-password',
    name: 'reset-password',
    component: () => import('~/pages/auth/ResetPassword.vue'),
    meta: { layout: 'unauthenticated', guest: true },
  },
  {
    path: '/verify-email',
    name: 'verify-email',
    component: () => import('~/pages/auth/VerifyEmail.vue'),
    meta: { layout: 'unauthenticated', public: true },
  },
  {
    path: '/invites/:id',
    name: 'invite',
    component: () => import('~/pages/invites/Accept.vue'),
    meta: { layout: 'unauthenticated', public: true },
  },
  {
    path: '/access-denied',
    name: 'access-denied',
    component: () => import('~/pages/AccessDenied.vue'),
    meta: { layout: 'app' },
  },
  {
    path: '/users',
    name: 'users',
    component: () => import('~/pages/users/Index.vue'),
    meta: { layout: 'app' },
  },
  {
    path: '/account/profile',
    name: 'account-profile',
    component: () => import('~/pages/account/Profile.vue'),
    meta: { layout: 'app' },
  },
  {
    path: '/account/security',
    name: 'account-security',
    component: () => import('~/pages/account/Security.vue'),
    meta: { layout: 'app' },
  },
  { path: '/:pathMatch(.*)*', name: 'not-found', redirect: '/' },
]

export const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior: (to, _from, savedPosition) =>
    savedPosition ?? (to.hash ? { el: to.hash } : { top: 0 }),
})

// No view transitions here on purpose: they were tried and reverted.
router.beforeEach(async (to) => {
  const auth = useAuth()

  await auth.init()

  const routeName = typeof to.name === 'string' ? to.name : null

  if (to.meta.guest && auth.isAuthenticated.value) {
    return { name: 'dashboard' }
  }

  // Fail closed: a protected route needs a ready, authenticated session.
  if (!to.meta.guest && !to.meta.public && (!auth.ready.value || !auth.isAuthenticated.value)) {
    return { name: 'login', query: { return: to.fullPath } }
  }

  if (routeName === 'access-denied' || !routeName || !getRouteAccessRequirement(routeName)) {
    return true
  }

  if (!canAccessRouteByName(routeName, { me: auth.me.value, routeName })) {
    return { name: 'access-denied', query: { from: to.fullPath, scope: 'global' } }
  }

  return true
})
