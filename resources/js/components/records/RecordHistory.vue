<script setup lang="ts">
import Icon from '~/components/Icon.vue'
import { Avatar } from '~/components/ui/avatar'
import { Skeleton } from '~/components/ui/skeleton'
import { useFormat } from '~/composables/useFormat'
import { useReactiveQuery } from '~/lib/reactive'
import { useI18n } from '~/plugins/i18n'

/**
 * What happened to one record, newest first, and live: a save on another
 * screen adds its line here. Works on any model that uses Auditable; the
 * type is its table. Field names read `<type>.fields.<field>`, the labels
 * make:feature writes, and fall back to the column name.
 */
const props = defineProps<{ type: string; id: string }>()

const { t, te } = useI18n()
const { relative, dateTime } = useFormat()

const history = useReactiveQuery('audit.history', () => ({ type: props.type, id: props.id }))

const entries = computed(() => history.data.value ?? [])

const EVENT_ICONS: Record<string, string> = {
  created: 'lucide:plus',
  updated: 'lucide:pencil',
  deleted: 'lucide:trash-2',
  restored: 'lucide:rotate-ccw',
}

const fieldLabel = (field: string): string => {
  const key = `${props.type}.fields.${field}`

  return te(key) ? t(key) : field.replaceAll('_', ' ')
}

const actor = (entry: App.Data.AuditEntryData): string =>
  entry.impersonatorName
    ? t('records.history.impersonated', {
        name: entry.actorName ?? '',
        impersonator: entry.impersonatorName,
      })
    : (entry.actorName ?? t('records.history.system'))

/** A create or a delete lists every field; the one side it has is the value. */
const showsBoth = (entry: App.Data.AuditEntryData): boolean => entry.event === 'updated'
</script>

<template>
  <div
    v-if="history.isPending.value"
    class="grid gap-2"
  >
    <Skeleton class="h-12" />
    <Skeleton class="h-12" />
  </div>

  <p
    v-else-if="entries.length === 0"
    class="rounded-xl border border-dashed border-border py-8 text-center text-sm text-muted"
  >
    {{ t('records.history.empty') }}
  </p>

  <ol
    v-else
    class="relative grid before:absolute before:top-4 before:bottom-4 before:left-[15px] before:w-px before:bg-border"
  >
    <li
      v-for="entry in entries"
      :key="entry.id"
      class="relative flex items-start gap-3 py-2.5 text-sm"
    >
      <span class="relative z-10 shrink-0">
        <Avatar
          v-if="entry.actorName"
          :name="entry.actorName"
          :avatar="entry.actorAvatarUrl"
        />
        <span
          v-else
          class="grid size-8 place-items-center rounded-xl border border-border bg-card text-muted"
        >
          <Icon
            name="lucide:cpu"
            size="14"
            aria-hidden="true"
          />
        </span>
      </span>

      <div class="grid min-w-0 flex-1 gap-1.5 pt-1">
        <div class="flex items-baseline justify-between gap-3">
          <span class="flex min-w-0 items-center gap-1.5">
            <Icon
              :name="EVENT_ICONS[entry.event] ?? 'lucide:activity'"
              size="13"
              class="shrink-0 text-muted"
              aria-hidden="true"
            />
            <span class="truncate">
              <span class="font-medium text-primary">{{ actor(entry) }}</span>
              {{ t(`records.history.events.${entry.event}`) }}
            </span>
          </span>
          <time
            class="shrink-0 text-xs text-muted"
            :datetime="entry.createdAt"
            :title="dateTime(entry.createdAt)"
          >
            {{ relative(entry.createdAt) }}
          </time>
        </div>

        <dl
          v-if="entry.changes.length > 0"
          class="grid gap-1 rounded-lg bg-muted-background px-3 py-2 text-xs"
        >
          <div
            v-for="change in entry.changes"
            :key="change.field"
            class="grid grid-cols-[minmax(6rem,auto)_minmax(0,1fr)] gap-3"
          >
            <dt class="truncate text-muted">{{ fieldLabel(change.field) }}</dt>
            <dd
              v-if="change.redacted"
              class="text-muted italic"
            >
              {{ t('records.history.redacted') }}
            </dd>
            <dd
              v-else
              class="min-w-0 break-words text-primary"
            >
              <template v-if="showsBoth(entry)">
                <del class="text-muted">{{ change.before ?? '–' }}</del>
                <span
                  class="px-1 text-muted"
                  aria-hidden="true"
                  >→</span
                >
                <span>{{ change.after ?? '–' }}</span>
              </template>
              <span v-else>{{ change.after ?? change.before ?? '–' }}</span>
            </dd>
          </div>
        </dl>
      </div>
    </li>
  </ol>
</template>
