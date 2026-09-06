<script setup lang="ts">
import { computed, nextTick, ref } from 'vue'
import { toast } from 'vue-sonner'

import Icon from '~/components/Icon.vue'
import { Button } from '~/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '~/components/ui/dropdown-menu'
import { Input } from '~/components/ui/input'
import { SimpleTooltip } from '~/components/ui/tooltip'
import { useConfirm } from '~/composables/useConfirm'
import type { UseSavedViews } from '~/composables/useSavedViews'
import { toastError } from '~/lib/toast-error'
import { useI18n } from '~/plugins/i18n'

/**
 * The saved states of one list.
 *
 * The trigger is the bookmark alone, so the toolbar spends one square on it
 * rather than a name that grows with the name. The "where am I" answer the
 * name used to give is kept three ways: the icon gains its check mark when a
 * view is applied, its accessible name and tooltip become that view's name,
 * and the applied view is ticked in the menu. Shape, not colour, carries it.
 *
 * Saving asks for a name inline rather than in a dialog: naming a view is one
 * field, and a modal for one field is a modal too many.
 */
const props = defineProps<{
  views: UseSavedViews
  /** Whether anything is narrowing the list. Nothing to save otherwise. */
  dirty: boolean
}>()

const { t } = useI18n()
const { confirm } = useConfirm()

const open = ref(false)
const naming = ref(false)
const name = ref('')
// `Input` exposes focus(); the ref is the component, not the element.
const nameInput = ref<InstanceType<typeof Input> | null>(null)

const active = computed(() => props.views.current.value)

const label = computed(() =>
  active.value ? t('views.triggerActive', { name: active.value.name }) : t('views.trigger')
)

const startNaming = () => {
  naming.value = true
  name.value = ''
  void nextTick(() => nameInput.value?.focus())
}

const save = async () => {
  const trimmed = name.value.trim()
  if (trimmed === '') return

  try {
    await props.views.save(trimmed)
    naming.value = false
    open.value = false
    toast.success(t('views.saved', { name: trimmed }))
  } catch (error) {
    toastError(t, 'views.error', error)
  }
}

const apply = (view: App.Data.SavedViewData) => {
  props.views.apply(view)
  open.value = false
}

const remove = async (view: App.Data.SavedViewData) => {
  const confirmed = await confirm({
    title: t('views.deleteTitle'),
    message: t('views.deleteConfirm', { name: view.name }),
    confirmLabel: t('actions.remove'),
    variant: 'destructive',
  })

  if (!confirmed) return

  try {
    await props.views.remove(view.id)
    toast.success(t('views.deleted', { name: view.name }))
  } catch (error) {
    toastError(t, 'views.error', error)
  }
}

const toggleDefault = async (view: App.Data.SavedViewData) => {
  try {
    await props.views.setDefault(view.id, !view.isDefault)
  } catch (error) {
    toastError(t, 'views.error', error)
  }
}
</script>

<template>
  <DropdownMenu v-model:open="open">
    <DropdownMenuTrigger as-child>
      <SimpleTooltip
        :tooltip="label"
        as-child
      >
        <Button
          variant="outline"
          size="icon"
          class="max-sm:size-10"
          :aria-label="label"
        >
          <Icon
            :name="active ? 'lucide:bookmark-check' : 'lucide:bookmark'"
            :class="active ? 'text-accent' : ''"
            aria-hidden="true"
          />
        </Button>
      </SimpleTooltip>
    </DropdownMenuTrigger>

    <DropdownMenuContent
      align="end"
      class="w-64"
    >
      <DropdownMenuLabel>{{ t('views.title') }}</DropdownMenuLabel>

      <p
        v-if="views.views.value.length === 0"
        class="px-2 py-3 text-sm text-muted"
      >
        {{ t('views.empty') }}
      </p>

      <DropdownMenuItem
        v-for="view in views.views.value"
        :key="view.id"
        class="group justify-between gap-2"
        @select="apply(view)"
      >
        <span class="flex min-w-0 items-center gap-2">
          <Icon
            :name="views.current.value?.id === view.id ? 'lucide:check' : 'lucide:list-filter'"
            size="14"
            class="shrink-0 text-muted"
            aria-hidden="true"
          />
          <span class="truncate">{{ view.name }}</span>
        </span>

        <span class="flex shrink-0 items-center">
          <button
            type="button"
            class="rounded p-1 text-muted opacity-0 transition-opacity group-hover:opacity-100 focus-visible:opacity-100 hover:text-primary aria-pressed:text-accent aria-pressed:opacity-100"
            :aria-pressed="view.isDefault"
            :aria-label="t('views.makeDefault', { name: view.name })"
            @click.stop.prevent="toggleDefault(view)"
          >
            <Icon
              :name="view.isDefault ? 'lucide:star' : 'lucide:star-off'"
              size="13"
            />
          </button>
          <button
            type="button"
            class="rounded p-1 text-muted opacity-0 transition-opacity group-hover:opacity-100 focus-visible:opacity-100 hover:text-destructive"
            :aria-label="t('views.delete', { name: view.name })"
            @click.stop.prevent="remove(view)"
          >
            <Icon
              name="lucide:trash-2"
              size="13"
            />
          </button>
        </span>
      </DropdownMenuItem>

      <DropdownMenuSeparator />

      <div
        v-if="naming"
        class="p-1"
        @keydown.stop
      >
        <Input
          ref="nameInput"
          v-model="name"
          :placeholder="t('views.namePlaceholder')"
          :aria-label="t('views.namePlaceholder')"
          class="h-8"
          @keydown.enter.prevent="save"
          @keydown.esc.prevent="naming = false"
        />
        <div class="mt-1 flex justify-end gap-1">
          <Button
            variant="ghost"
            size="xs"
            @click="naming = false"
          >
            {{ t('actions.cancel') }}
          </Button>
          <Button
            variant="accent"
            size="xs"
            :disabled="name.trim() === '' || views.isSaving.value"
            @click="save"
          >
            {{ t('actions.save') }}
          </Button>
        </div>
      </div>

      <DropdownMenuItem
        v-else
        :disabled="!dirty"
        @select.prevent="startNaming"
      >
        <Icon
          name="lucide:plus"
          size="14"
          aria-hidden="true"
        />
        {{ t('views.save') }}
      </DropdownMenuItem>
    </DropdownMenuContent>
  </DropdownMenu>
</template>
