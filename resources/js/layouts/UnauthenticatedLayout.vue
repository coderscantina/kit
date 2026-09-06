<script setup lang="ts">
import ErrorBoundary from '~/components/ErrorBoundary.vue'
import Icon from '~/components/Icon.vue'
import { Button } from '~/components/ui/button'
import { useColorMode } from '~/composables/useColorMode'
import { appConfig } from '~/lib/app-config'
import { useI18n } from '~/plugins/i18n'

const { t } = useI18n()
const { isDark, setMode } = useColorMode()

const brand = appConfig.brand

/** Three lines, editable in `en.json`; a derived app never touches this file. */
const points = ['auth.marketing.pointOne', 'auth.marketing.pointTwo', 'auth.marketing.pointThree']
</script>

<template>
  <div class="grid min-h-svh lg:grid-cols-2">
    <!-- The brand half is decoration, so it is the half that goes first on a
         small screen; nobody signs in by reading the tagline. -->
    <aside
      class="relative hidden flex-col justify-between overflow-hidden bg-surface p-10 lg:flex"
      aria-hidden="true"
    >
      <div
        class="pointer-events-none absolute -top-32 -left-24 size-96 rounded-full bg-accent/15 blur-3xl"
      />
      <div
        class="pointer-events-none absolute -right-24 -bottom-32 size-96 rounded-full bg-ai/10 blur-3xl"
      />

      <div class="relative flex items-center gap-2.5 text-lg font-semibold text-primary">
        <component
          :is="brand.logo"
          v-if="brand.logo"
          class="size-6"
        />
        <Icon
          v-else
          :name="brand.icon"
          size="24"
          class="text-accent"
        />
        {{ brand.name }}
      </div>

      <div class="relative max-w-md">
        <p class="text-3xl font-semibold text-balance text-primary">
          {{ t('auth.marketing.headline') }}
        </p>
        <ul class="mt-8 grid gap-3">
          <li
            v-for="point in points"
            :key="point"
            class="flex items-start gap-3 text-sm text-foreground"
          >
            <Icon
              name="lucide:check"
              size="16"
              class="mt-0.5 shrink-0 text-accent"
            />
            {{ t(point) }}
          </li>
        </ul>
      </div>

      <p class="relative text-xs text-muted">{{ t('auth.marketing.footer') }}</p>
    </aside>

    <main class="flex flex-col overflow-x-clip bg-background">
      <div class="flex justify-end p-3">
        <!-- Before sign-in is exactly when someone with light sensitivity
             needs the switch, so it is not hidden behind the account menu. -->
        <Button
          variant="ghost"
          size="icon"
          :aria-label="t('colorMode.label')"
          @click="setMode(isDark ? 'light' : 'dark')"
        >
          <Icon :name="isDark ? 'lucide:sun' : 'lucide:moon'" />
        </Button>
      </div>

      <div class="flex flex-1 items-center justify-center px-4 pb-16 sm:px-6">
        <div class="w-full max-w-sm">
          <ErrorBoundary>
            <slot />
          </ErrorBoundary>
        </div>
      </div>
    </main>
  </div>
</template>
