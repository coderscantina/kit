<script setup lang="ts">
import Icon from '~/components/Icon.vue'
import { Input } from '~/components/ui/input'
import { Label } from '~/components/ui/label'
import { useI18n } from '~/plugins/i18n'

const props = defineProps<{
  id: string
  label: string
  type?: string
  autocomplete?: string
  error?: string
  /** Hint under the field. Say what the rule is, not that there is one. */
  description?: string
  required?: boolean
  autofocus?: boolean
}>()

const { t } = useI18n()

const model = defineModel<string>({ default: '' })

const revealed = ref(false)

const isPassword = computed(() => props.type === 'password')
const inputType = computed(() =>
  isPassword.value && revealed.value ? 'text' : (props.type ?? 'text')
)

/**
 * Both the hint and the error are announced, in that order, because a field
 * that only announces its error leaves the rule it broke unsaid.
 */
const describedBy = computed(() => {
  const ids = [
    props.description ? `${props.id}-description` : null,
    props.error ? `${props.id}-error` : null,
  ].filter(Boolean)

  return ids.length > 0 ? ids.join(' ') : undefined
})
</script>

<template>
  <div class="grid gap-1.5">
    <Label :for="id">
      {{ label }}
      <span
        v-if="required"
        aria-hidden="true"
        class="text-muted"
        >*</span
      >
    </Label>

    <div class="relative">
      <Input
        :id="id"
        v-model="model"
        :type="inputType"
        :autocomplete="autocomplete"
        :required="required"
        :autofocus="autofocus"
        :aria-invalid="error ? true : undefined"
        :aria-describedby="describedBy"
        :class="[isPassword && 'pr-10', error && 'border-destructive']"
      />
      <button
        v-if="isPassword"
        type="button"
        class="absolute inset-y-0 right-0 flex w-10 cursor-pointer items-center justify-center rounded-r-md text-muted hover:text-primary"
        :aria-label="revealed ? t('auth.fields.hidePassword') : t('auth.fields.showPassword')"
        :aria-pressed="revealed"
        @click="revealed = !revealed"
      >
        <Icon :name="revealed ? 'lucide:eye-off' : 'lucide:eye'" />
      </button>
    </div>

    <p
      v-if="description"
      :id="`${id}-description`"
      class="text-xs text-muted"
    >
      {{ description }}
    </p>
    <p
      v-if="error"
      :id="`${id}-error`"
      class="text-xs text-destructive"
    >
      {{ error }}
    </p>
  </div>
</template>
