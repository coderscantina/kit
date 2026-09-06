<script setup lang="ts">
import AppHeader from '~/components/app/AppHeader.vue'
import AppMobileSidebar from '~/components/app/AppMobileSidebar.vue'
import AppSidebar from '~/components/app/AppSidebar.vue'
import AppTabBar from '~/components/app/AppTabBar.vue'
import ImpersonationBanner from '~/components/app/ImpersonationBanner.vue'
import SkipLink from '~/components/app/SkipLink.vue'
import ErrorBoundary from '~/components/ErrorBoundary.vue'
import { useCurrentPageMeta } from '~/composables/usePageMeta'
import { useShortcut } from '~/composables/useShortcuts'
import { useSidebar } from '~/composables/useSidebar'
import { appConfig } from '~/lib/app-config'
import { useI18n } from '~/plugins/i18n'

const { t } = useI18n()
const router = useRouter()
const sidebar = useSidebar()
const { title } = useCurrentPageMeta()

/** Read by the live region, so a route change is announced, not just painted. */
const announcement = ref('')

/**
 * A single-page navigation leaves focus on the link that is now gone, which
 * drops screen-reader and keyboard users back at the top of the document. Move
 * focus to the new page instead. Skipped for the first render: stealing focus
 * on load is its own bug.
 */
let navigated = false

router.afterEach((to, from) => {
  if (!navigated) {
    navigated = true
    return
  }

  // A hash link is a jump inside the page the user is already reading.
  if (to.path === from.path && to.hash) return

  void nextTick(() => {
    document.getElementById('main-content')?.focus({ preventScroll: true })
    announcement.value = title.value
  })
})

useShortcut({
  keys: 'mod+b',
  description: () => t('shortcuts.toggleSidebar'),
  enabled: () => sidebar.collapsible,
  handler: () => sidebar.toggle(),
})

/**
 * `/` jumps to the page's own search field. Pages opt in by marking it with
 * `data-shortcut-search`; `input[type=search]` is picked up for free.
 */
useShortcut({
  keys: '/',
  description: () => t('shortcuts.search'),
  enabled: () => appConfig.features.pageSearchShortcut,
  handler: () => {
    const root = document.getElementById('main-content')
    if (!root) return

    const candidates = root.querySelectorAll<HTMLInputElement>(
      '[data-shortcut-search], input[type="search"]'
    )

    for (const input of candidates) {
      if (input.disabled || input.offsetParent === null) continue
      input.focus()
      input.select()
      return
    }
  },
})
</script>

<template>
  <div class="flex min-h-svh">
    <SkipLink />
    <!-- Sticky rather than scrolling with the page: the window scrolls, and a
         nav that leaves the screen is a nav you have to scroll back for. -->
    <AppSidebar class="sticky top-0 h-svh" />
    <AppMobileSidebar />

    <div class="flex min-w-0 flex-1 flex-col">
      <AppHeader />
      <ImpersonationBanner />

      <!-- overflow-x-clip, not overflow-hidden: hidden breaks every sticky element inside. -->
      <!-- Inside the shell, not around it: a page that fails to render leaves the
           sidebar usable, so navigating away is still a way out. -->
      <!-- The bottom padding on a phone is the tab bar plus the home indicator,
           so the last row of a page is never hidden under either. -->
      <main
        id="main-content"
        tabindex="-1"
        class="min-w-0 flex-1 overflow-x-clip px-shell-gutter-x py-shell-gutter pb-[calc(var(--shell-gutter)+var(--shell-tabbar-height)+env(safe-area-inset-bottom))] focus:outline-none lg:pb-shell-gutter"
      >
        <ErrorBoundary>
          <slot />
        </ErrorBoundary>
      </main>
    </div>

    <AppTabBar />

    <span
      aria-live="polite"
      aria-atomic="true"
      class="sr-only"
    >
      {{ announcement }}
    </span>
  </div>
</template>
