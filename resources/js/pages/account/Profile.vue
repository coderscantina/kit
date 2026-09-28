<script setup lang="ts">
import { toast } from 'vue-sonner'

import { api } from '~/api'
import AppearanceSection from '~/components/account/AppearanceSection.vue'
import AvatarField from '~/components/account/AvatarField.vue'
import EmailSection from '~/components/account/EmailSection.vue'
import SettingsPage from '~/components/account/SettingsPage.vue'
import SettingsSection from '~/components/account/SettingsSection.vue'
import FormField from '~/components/FormField.vue'
import { Button } from '~/components/ui/button'
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
import { usePageMeta } from '~/composables/usePageMeta'
import { useUnsavedChanges } from '~/composables/useUnsavedChanges'
import { isSupportedLocale, setLocale, useI18n, type LocaleCode } from '~/plugins/i18n'

const { t, locales } = useI18n()
const auth = useAuth()
const errors = useFormErrors()

usePageMeta(() => ({
  title: t('account.profile.title'),
  breadcrumbs: [{ label: t('account.title') }, { label: t('account.profile.title') }],
}))

const name = ref(auth.user.value?.name ?? '')
const locale = ref<LocaleCode>(
  isSupportedLocale(auth.user.value?.locale ?? '') ? (auth.user.value?.locale as LocaleCode) : 'en'
)
const saving = ref(false)

const localeName = computed(
  () => locales.find((entry) => entry.code === locale.value)?.name ?? locale.value
)

/** Save only lights up once something differs from what the server has. */
const dirty = computed(
  () =>
    name.value.trim() !== (auth.user.value?.name ?? '') || locale.value !== auth.user.value?.locale
)

useUnsavedChanges(() => dirty.value && !saving.value)

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
  <SettingsPage
    :title="t('account.profile.title')"
    :description="t('account.profile.description')"
  >
    <SettingsSection
      id="details"
      :title="t('account.profile.details')"
      :description="t('account.profile.detailsDescription')"
    >
      <form
        id="profile-form"
        class="grid gap-5"
        @submit.prevent="save"
      >
        <AvatarField
          :name="auth.user.value?.name ?? ''"
          :avatar-url="auth.user.value?.avatarUrl ?? null"
          @changed="onAvatarChanged"
        />
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
      </form>

      <template #footer>
        <Button
          variant="primary"
          type="submit"
          form="profile-form"
          :loading="saving"
          :disabled="!dirty"
        >
          {{ t('actions.save') }}
        </Button>
      </template>
    </SettingsSection>

    <EmailSection />

    <AppearanceSection />
  </SettingsPage>
</template>
