<script setup lang="ts">
import { api } from '~/api'
import FormField from '~/components/FormField.vue'
import { Button } from '~/components/ui/button'
import { Dialog, DialogContent, DialogHeaderCombined } from '~/components/ui/dialog'
import { Label } from '~/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '~/components/ui/select'
import { useFormErrors } from '~/composables/useFormErrors'
import { useI18n } from '~/plugins/i18n'

const props = defineProps<{ roles: App.Data.RoleData[] }>()

const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ invited: [email: string] }>()

const { t } = useI18n()
const errors = useFormErrors()

const email = ref('')
const role = ref('member')
const sending = ref(false)

const roleName = computed(() => props.roles.find((entry) => entry.key === role.value)?.name ?? '')

const reset = () => {
  email.value = ''
  role.value = 'member'
  errors.clear()
}

watch(open, (value) => {
  if (!value) reset()
})

const submit = async () => {
  sending.value = true
  errors.clear()
  try {
    await api.invites.create({ email: email.value, role: role.value })
    emit('invited', email.value)
    open.value = false
  } catch (error) {
    errors.capture(error)
  } finally {
    sending.value = false
  }
}
</script>

<template>
  <Dialog v-model:open="open">
    <DialogContent class="max-w-md">
      <DialogHeaderCombined
        :title="t('users.invites.title')"
        :description="t('users.invites.description')"
      />
      <form
        class="grid gap-4"
        @submit.prevent="submit"
      >
        <FormField
          id="invite-email"
          v-model="email"
          type="email"
          autocomplete="off"
          :label="t('auth.fields.email')"
          :error="errors.fields.value.email"
          required
          autofocus
        />
        <div class="grid gap-1.5">
          <Label for="invite-role">{{ t('users.role') }}</Label>
          <Select v-model="role">
            <SelectTrigger id="invite-role">
              <SelectValue>{{ roleName }}</SelectValue>
            </SelectTrigger>
            <SelectContent>
              <SelectItem
                v-for="entry in roles"
                :key="entry.key"
                :value="entry.key"
              >
                {{ entry.name }}
              </SelectItem>
            </SelectContent>
          </Select>
          <p
            v-if="errors.fields.value.role"
            class="text-xs text-destructive"
          >
            {{ errors.fields.value.role }}
          </p>
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
            :loading="sending"
            :disabled="!email"
          >
            {{ t('users.invites.send') }}
          </Button>
        </div>
      </form>
    </DialogContent>
  </Dialog>
</template>
