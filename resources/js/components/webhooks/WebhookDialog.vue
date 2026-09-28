<script setup lang="ts">
import { ValidationError } from '@kit/reactive-vue'

import FormField from '~/components/FormField.vue'
import { Button } from '~/components/ui/button'
import { Checkbox } from '~/components/ui/checkbox'
import { Dialog, DialogContent, DialogHeaderCombined } from '~/components/ui/dialog'
import { InputField } from '~/components/ui/form'
import { Label } from '~/components/ui/label'
import { Switch } from '~/components/ui/switch'
import { useReactiveMutation, useReactiveQuery } from '~/lib/reactive'
import { useI18n } from '~/plugins/i18n'

/**
 * Creates an endpoint or edits one. The signing secret is shown after a
 * create and after a rotation, the only two answers that carry it, so the
 * dialog stays on it until the user closes it.
 */
const props = defineProps<{ endpoint: App.Data.WebhookEndpointData | null }>()
const open = defineModel<boolean>('open', { default: false })

const { t } = useI18n()

const catalog = useReactiveQuery('webhooks.events', undefined, { enabled: open })

const url = ref('')
const description = ref('')
const events = ref<string[]>([])
const active = ref(true)
const secret = ref<string | null>(null)

/** `users.created` … grouped under `users`, so a whole type is one row. */
const groups = computed(() => {
  const byType = new Map<string, string[]>()

  for (const event of catalog.data.value?.events ?? []) {
    const [type = '', change = ''] = event.split('.')
    byType.set(type, [...(byType.get(type) ?? []), change])
  }

  return [...byType].map(([type, changes]) => ({ type, changes }))
})

const has = (pattern: string): boolean => events.value.includes(pattern)

const toggle = (pattern: string, on: boolean | 'indeterminate') => {
  events.value =
    on === true ? [...events.value, pattern] : events.value.filter((e) => e !== pattern)
}

watch(open, (value) => {
  if (!value) return
  url.value = props.endpoint?.url ?? ''
  description.value = props.endpoint?.description ?? ''
  events.value = [...(props.endpoint?.events ?? [])]
  active.value = props.endpoint?.active ?? true
  secret.value = null
})

const create = useReactiveMutation('webhooks.create', {
  onSuccess: (result) => {
    secret.value = result.secret
  },
})

const update = useReactiveMutation('webhooks.update', {
  onSuccess: () => {
    open.value = false
  },
})

const rotate = useReactiveMutation('webhooks.rotateSecret', {
  onSuccess: (result) => {
    secret.value = result.secret
  },
})

const pending = computed(() => create.isPending.value || update.isPending.value)

const fieldError = (field: string): string | undefined => {
  const error = create.error.value ?? update.error.value

  return error instanceof ValidationError ? error.first(field) : undefined
}

const save = () => {
  const args = {
    url: url.value,
    description: description.value || null,
    events: events.value,
    active: active.value,
  }

  if (props.endpoint) update.mutate({ ...args, id: props.endpoint.id })
  else create.mutate({ ...args, id: null })
}
</script>

<template>
  <Dialog v-model:open="open">
    <DialogContent
      class="max-w-xl"
      @interact-outside="(event) => secret && event.preventDefault()"
    >
      <template v-if="secret">
        <DialogHeaderCombined
          :title="t('webhooks.secretTitle')"
          :description="t('webhooks.secretDescription')"
        />
        <InputField
          name="webhook-secret"
          :model-value="secret"
          :actions="['copy']"
          input-class="font-mono"
          readonly
        />
        <div class="flex justify-end">
          <Button
            variant="primary"
            @click="open = false"
          >
            {{ t('account.actions.done') }}
          </Button>
        </div>
      </template>

      <template v-else>
        <DialogHeaderCombined
          :title="endpoint ? t('webhooks.editTitle') : t('webhooks.createTitle')"
          :description="t('webhooks.formDescription')"
        />
        <form
          class="grid gap-4"
          @submit.prevent="save"
        >
          <FormField
            id="webhook-url"
            v-model="url"
            type="url"
            :label="t('webhooks.url')"
            :description="t('webhooks.urlHint')"
            :error="fieldError('url')"
            required
            autofocus
          />
          <FormField
            id="webhook-description"
            v-model="description"
            :label="t('webhooks.descriptionLabel')"
            :error="fieldError('description')"
          />

          <fieldset class="grid gap-2">
            <legend class="mb-1 text-sm font-medium text-primary">
              {{ t('webhooks.events') }}
            </legend>
            <label class="flex items-center gap-2 text-sm">
              <Checkbox
                :model-value="has('*')"
                @update:model-value="(on) => toggle('*', on)"
              />
              {{ t('webhooks.everything') }}
            </label>
            <div
              v-for="group in groups"
              :key="group.type"
              class="flex flex-wrap items-center gap-x-4 gap-y-1.5 rounded-lg border border-border px-3 py-2 text-sm"
              :class="has('*') ? 'opacity-50' : ''"
            >
              <label class="flex items-center gap-2 font-medium">
                <Checkbox
                  :model-value="has(`${group.type}.*`)"
                  :disabled="has('*')"
                  @update:model-value="(on) => toggle(`${group.type}.*`, on)"
                />
                <code class="font-mono text-xs">{{ group.type }}.*</code>
              </label>
              <label
                v-for="change in group.changes"
                :key="change"
                class="flex items-center gap-2 text-muted"
              >
                <Checkbox
                  :model-value="has(`${group.type}.${change}`)"
                  :disabled="has('*') || has(`${group.type}.*`)"
                  @update:model-value="(on) => toggle(`${group.type}.${change}`, on)"
                />
                {{ change }}
              </label>
            </div>
            <p
              v-if="fieldError('events')"
              class="text-xs text-destructive"
            >
              {{ fieldError('events') }}
            </p>
          </fieldset>

          <div
            v-if="endpoint"
            class="flex items-center justify-between gap-3"
          >
            <Label
              for="webhook-active"
              class="grid gap-0.5"
            >
              {{ t('webhooks.active') }}
              <span class="text-xs font-normal text-muted">{{ t('webhooks.activeHint') }}</span>
            </Label>
            <Switch
              id="webhook-active"
              v-model="active"
            />
          </div>

          <div class="flex items-center justify-between gap-2">
            <Button
              v-if="endpoint"
              type="button"
              variant="ghost"
              :loading="rotate.isPending.value"
              @click="rotate.mutate({ id: endpoint.id })"
            >
              {{ t('webhooks.rotateSecret') }}
            </Button>
            <span v-else />
            <div class="flex gap-2">
              <Button
                type="button"
                variant="ghost"
                @click="open = false"
              >
                {{ t('actions.cancel') }}
              </Button>
              <Button
                variant="primary"
                type="submit"
                :loading="pending"
                :disabled="!url || events.length === 0"
              >
                {{ endpoint ? t('actions.save') : t('webhooks.create') }}
              </Button>
            </div>
          </div>
        </form>
      </template>
    </DialogContent>
  </Dialog>
</template>
