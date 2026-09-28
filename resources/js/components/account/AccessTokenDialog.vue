<script setup lang="ts">
import { api } from '~/api'
import FormField from '~/components/FormField.vue'
import PasswordConfirmDialog from '~/components/PasswordConfirmDialog.vue'
import { Button } from '~/components/ui/button'
import { Checkbox } from '~/components/ui/checkbox'
import { Dialog, DialogContent, DialogHeaderCombined } from '~/components/ui/dialog'
import { InputField } from '~/components/ui/form'
import { Label } from '~/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '~/components/ui/select'
import { useAuth } from '~/composables/useAuth'
import { useFormErrors } from '~/composables/useFormErrors'
import { useStepUp } from '~/composables/useStepUp'
import { useI18n } from '~/plugins/i18n'

/**
 * Mints a token in two steps: choose what it may do, then copy it. The
 * second step is the only time the token exists outside its hash, so the
 * dialog says so and does not close on its own.
 */
const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ created: [] }>()

const { t } = useI18n()
const auth = useAuth()
const errors = useFormErrors()
const stepUp = useStepUp()

/** `app.access` is added by the server to every token; offering it would be a choice with one answer. */
const grantable = computed(() =>
  (auth.me.value?.abilities ?? []).filter((ability) => ability !== 'app.access')
)

const LIFETIMES = ['30', '90', '365', 'never'] as const

const name = ref('')
const abilities = ref<string[]>([])
const lifetime = ref<(typeof LIFETIMES)[number]>('90')
const saving = ref(false)
const plainText = ref<string | null>(null)

const toggle = (ability: string, on: boolean | 'indeterminate') => {
  abilities.value =
    on === true
      ? [...abilities.value, ability]
      : abilities.value.filter((entry) => entry !== ability)
}

watch(open, (value) => {
  if (value) return
  name.value = ''
  abilities.value = []
  lifetime.value = '90'
  plainText.value = null
  errors.clear()
})

const submit = async () => {
  saving.value = true
  errors.clear()
  try {
    const result = await stepUp.run(() =>
      api.account.createToken({
        name: name.value,
        abilities: abilities.value,
        expires_in_days: lifetime.value === 'never' ? null : Number(lifetime.value),
      })
    )
    if (result) {
      plainText.value = result.plainTextToken
      emit('created')
    }
  } catch (error) {
    errors.capture(error)
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <Dialog v-model:open="open">
    <DialogContent
      class="max-w-lg"
      @interact-outside="(event) => plainText && event.preventDefault()"
    >
      <template v-if="plainText">
        <DialogHeaderCombined
          :title="t('tokens.createdTitle')"
          :description="t('tokens.createdDescription')"
        />
        <InputField
          name="token"
          :model-value="plainText"
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
          :title="t('tokens.createTitle')"
          :description="t('tokens.createDescription')"
        />
        <form
          class="grid gap-4"
          @submit.prevent="submit"
        >
          <FormField
            id="token-name"
            v-model="name"
            :label="t('tokens.name')"
            :description="t('tokens.nameHint')"
            :error="errors.fields.value.name"
            required
            autofocus
          />

          <fieldset class="grid gap-2">
            <legend class="mb-1 text-sm font-medium text-primary">
              {{ t('tokens.abilities') }}
            </legend>
            <label
              v-for="ability in grantable"
              :key="ability"
              class="flex items-center gap-2 text-sm"
            >
              <Checkbox
                :model-value="abilities.includes(ability)"
                @update:model-value="(on) => toggle(ability, on)"
              />
              <code class="font-mono text-xs text-primary">{{ ability }}</code>
            </label>
            <p
              v-if="errors.fields.value.abilities"
              class="text-xs text-destructive"
            >
              {{ errors.fields.value.abilities }}
            </p>
          </fieldset>

          <div class="grid gap-1.5">
            <Label for="token-lifetime">{{ t('tokens.expires') }}</Label>
            <Select v-model="lifetime">
              <SelectTrigger id="token-lifetime">
                <SelectValue>{{ t(`tokens.lifetimes.${lifetime}`) }}</SelectValue>
              </SelectTrigger>
              <SelectContent>
                <SelectItem
                  v-for="entry in LIFETIMES"
                  :key="entry"
                  :value="entry"
                >
                  {{ t(`tokens.lifetimes.${entry}`) }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>

          <p
            v-if="errors.message.value"
            class="text-sm text-destructive"
          >
            {{ errors.message.value }}
          </p>

          <div class="flex justify-end gap-2">
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
              :loading="saving"
              :disabled="!name || abilities.length === 0"
            >
              {{ t('tokens.create') }}
            </Button>
          </div>
        </form>
      </template>
    </DialogContent>
  </Dialog>

  <PasswordConfirmDialog
    v-model:open="stepUp.confirmOpen.value"
    @confirmed="stepUp.onConfirmed"
  />
</template>
