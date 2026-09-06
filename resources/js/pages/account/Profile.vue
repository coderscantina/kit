<script setup lang="ts">
import { toast } from 'vue-sonner'

import { api } from '~/api'
import AvatarField from '~/components/account/AvatarField.vue'
import EmailChangeCard from '~/components/account/EmailChangeCard.vue'
import FormField from '~/components/FormField.vue'
import { Button } from '~/components/ui/button'
import { Card, CardContent, CardHeaderCombined } from '~/components/ui/card'
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
import { isSupportedLocale, setLocale, useI18n, type LocaleCode } from '~/plugins/i18n'

const { t, locales } = useI18n()
const auth = useAuth()
const errors = useFormErrors()

const name = ref(auth.user.value?.name ?? '')
const locale = ref<LocaleCode>(
  isSupportedLocale(auth.user.value?.locale ?? '') ? (auth.user.value?.locale as LocaleCode) : 'en'
)
const saving = ref(false)

const localeName = computed(
  () => locales.find((entry) => entry.code === locale.value)?.name ?? locale.value
)

const save = async () => {
  saving.value = true
  errors.clear()
  try {
    await api.account.updateProfile({ name: name.value, locale: locale.value })
    setLocale(locale.value)
    await auth.refresh()
    toast.success(t('account.profile.saved'))
  } catch (error) {
    errors.capture(error)
  } finally {
    saving.value = false
  }
}

const onAvatarChanged = async () => {
  await auth.refresh()
  toast.success(t('account.profile.avatarSaved'))
}
</script>

<template>
  <div class="max-w-2xl space-y-6">
    <h1 class="text-2xl font-semibold">{{ t('account.profile.title') }}</h1>

    <Card class="p-6">
      <CardHeaderCombined
        class="p-0 pb-4"
        :title="t('account.profile.avatar')"
        :description="t('account.profile.avatarDescription')"
      />
      <CardContent class="p-0">
        <AvatarField
          :name="auth.user.value?.name ?? ''"
          :avatar-url="auth.user.value?.avatarUrl ?? null"
          @changed="onAvatarChanged"
        />
      </CardContent>
    </Card>

    <Card class="p-6">
      <CardHeaderCombined
        class="p-0 pb-4"
        :title="t('account.profile.details')"
        :description="t('account.profile.detailsDescription')"
      />
      <CardContent class="p-0">
        <form
          class="grid gap-4"
          @submit.prevent="save"
        >
          <FormField
            id="name"
            v-model="name"
            autocomplete="name"
            :label="t('auth.fields.name')"
            :error="errors.fields.value.name"
            required
          />
          <div class="grid gap-1.5">
            <Label for="locale">{{ t('account.profile.language') }}</Label>
            <Select v-model="locale">
              <SelectTrigger id="locale">
                <SelectValue>{{ localeName }}</SelectValue>
              </SelectTrigger>
              <SelectContent>
                <SelectItem
                  v-for="entry in locales"
                  :key="entry.code"
                  :value="entry.code"
                >
                  {{ entry.name }}
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
          <div>
            <Button
              variant="primary"
              type="submit"
              :loading="saving"
            >
              {{ t('actions.save') }}
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>

    <EmailChangeCard />
  </div>
</template>
