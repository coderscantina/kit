<script setup lang="ts">
import { api } from '~/api'
import { Button } from '~/components/ui/button'
import { Dialog, DialogContent, DialogHeaderCombined } from '~/components/ui/dialog'
import { Input } from '~/components/ui/input'
import { Label } from '~/components/ui/label'
import { errorMessage } from '~/lib/toast-error'
import { useI18n } from '~/plugins/i18n'

/**
 * Re-enter the password for a step-up protected action. Emits `confirmed`
 * once the server accepted it; the caller retries its request.
 */
const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ confirmed: [] }>()

const { t } = useI18n()
const password = ref('')
const error = ref<string | null>(null)
const loading = ref(false)

const submit = async () => {
  loading.value = true
  error.value = null
  try {
    await api.auth.confirmPassword(password.value)
    password.value = ''
    open.value = false
    emit('confirmed')
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <Dialog v-model:open="open">
    <DialogContent class="max-w-md">
      <DialogHeaderCombined
        :title="t('auth.confirmPassword.title')"
        :description="t('auth.confirmPassword.description')"
      />
      <form
        class="space-y-4"
        @submit.prevent="submit"
      >
        <div class="space-y-2">
          <Label for="confirm-password">{{ t('auth.fields.password') }}</Label>
          <Input
            id="confirm-password"
            v-model="password"
            type="password"
            autocomplete="current-password"
            required
          />
        </div>
        <p
          v-if="error"
          class="text-sm text-destructive"
        >
          {{ error }}
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
            :loading="loading"
          >
            {{ t('actions.confirm') }}
          </Button>
        </div>
      </form>
    </DialogContent>
  </Dialog>
</template>
