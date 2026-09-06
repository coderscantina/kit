<script setup lang="ts">
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { toast } from 'vue-sonner'

import { api } from '~/api'
import PasswordConfirmDialog from '~/components/PasswordConfirmDialog.vue'
import { Button } from '~/components/ui/button'
import { Card } from '~/components/ui/card'
import { Input } from '~/components/ui/input'
import TableLoadingRow from '~/components/ui/TableLoadingRow.vue'
import { useAuth } from '~/composables/useAuth'
import { useStepUp } from '~/composables/useStepUp'
import { hasAbilityRequirement } from '~/lib/access-control'
import { queryKeys } from '~/lib/query-keys'
import { toastError } from '~/lib/toast-error'
import { useI18n } from '~/plugins/i18n'

const { t } = useI18n()
const auth = useAuth()
const queryClient = useQueryClient()
const stepUp = useStepUp()

const page = ref(1)
const users = useQuery({
  queryKey: computed(() => queryKeys.users(page.value)),
  queryFn: () => api.users.index(page.value),
  placeholderData: keepPreviousData,
})
const roles = useQuery({ queryKey: queryKeys.roles(), queryFn: () => api.users.roles() })
const invites = useQuery({
  queryKey: computed(() => queryKeys.invites(1)),
  queryFn: () => api.invites.index(1),
  enabled: computed(() => hasAbilityRequirement(auth.me.value, 'invites.view')),
})

const canManageRoles = computed(() => hasAbilityRequirement(auth.me.value, 'roles.manage'))
const canManageUsers = computed(() => hasAbilityRequirement(auth.me.value, 'users.manage'))
const canInvite = computed(() => hasAbilityRequirement(auth.me.value, 'invites.manage'))

const inviteForm = reactive({ email: '', role: 'member' })

const createInvite = useMutation({
  mutationFn: () => api.invites.create(inviteForm),
  onSuccess: () => {
    inviteForm.email = ''
    toast.success(t('users.invites.sent'))
    void queryClient.invalidateQueries({ queryKey: ['invites'] })
  },
  onError: (error) => toastError(t, 'users.invites.error', error),
})

const revokeInvite = useMutation({
  mutationFn: (id: string) => api.invites.destroy(id),
  onSuccess: () => queryClient.invalidateQueries({ queryKey: ['invites'] }),
  onError: (error) => toastError(t, 'users.invites.error', error),
})

const changeRole = async (user: App.Data.UserData, role: string) => {
  try {
    await stepUp.run(() => api.users.updateRole(user.id, role))
    await queryClient.invalidateQueries({ queryKey: ['users'] })
    toast.success(t('users.roleChanged'))
  } catch (error) {
    toastError(t, 'users.error', error)
  }
}

const removeUser = async (user: App.Data.UserData) => {
  if (!window.confirm(t('users.removeConfirm', { name: user.name }))) return
  try {
    await stepUp.run(() => api.users.destroy(user.id))
    await queryClient.invalidateQueries({ queryKey: ['users'] })
  } catch (error) {
    toastError(t, 'users.error', error)
  }
}
</script>

<template>
  <div class="space-y-6">
    <h1 class="text-2xl font-semibold">{{ t('users.title') }}</h1>

    <Card class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="border-b text-left text-muted">
          <tr>
            <th class="px-3 py-2 font-medium">{{ t('auth.fields.name') }}</th>
            <th class="px-3 py-2 font-medium">{{ t('auth.fields.email') }}</th>
            <th class="px-3 py-2 font-medium">{{ t('users.role') }}</th>
            <th class="px-3 py-2" />
          </tr>
        </thead>
        <!-- Dim while refetching with data present; skeleton rows only when there is nothing yet. -->
        <tbody
          :class="[
            'transition-opacity',
            users.isFetching.value && users.data.value ? 'opacity-50' : '',
          ]"
        >
          <TableLoadingRow
            v-if="users.isPending.value"
            :colspan="4"
            :rows="5"
          />
          <tr
            v-for="user in users.data.value?.data ?? []"
            :key="user.id"
            class="border-b last:border-0"
          >
            <td class="px-3 py-2">{{ user.name }}</td>
            <td class="px-3 py-2">{{ user.email }}</td>
            <td class="px-3 py-2">
              <select
                v-if="canManageRoles && user.id !== auth.user.value?.id"
                :value="user.role ?? ''"
                class="rounded-md border border-input bg-background px-2 py-1"
                @change="changeRole(user, ($event.target as HTMLSelectElement).value)"
              >
                <option
                  v-for="role in roles.data.value ?? []"
                  :key="role.key"
                  :value="role.key"
                >
                  {{ role.name }}
                </option>
              </select>
              <span v-else>{{ user.role ?? '–' }}</span>
            </td>
            <td class="px-3 py-2 text-right">
              <Button
                v-if="canManageUsers && user.id !== auth.user.value?.id"
                variant="ghost"
                size="sm"
                @click="removeUser(user)"
              >
                {{ t('actions.remove') }}
              </Button>
            </td>
          </tr>
        </tbody>
      </table>
      <div
        v-if="users.data.value && users.data.value.last_page > 1"
        class="flex items-center justify-end gap-2 border-t p-3 text-sm"
      >
        <Button
          variant="outline"
          size="sm"
          :disabled="page <= 1"
          @click="page--"
        >
          {{ t('actions.previous') }}
        </Button>
        <span>{{ page }} / {{ users.data.value.last_page }}</span>
        <Button
          variant="outline"
          size="sm"
          :disabled="page >= users.data.value.last_page"
          @click="page++"
        >
          {{ t('actions.next') }}
        </Button>
      </div>
    </Card>

    <Card
      v-if="invites.data.value || canInvite"
      class="p-6"
    >
      <h2 class="mb-4 font-medium">{{ t('users.invites.title') }}</h2>
      <form
        v-if="canInvite"
        class="mb-4 flex flex-wrap gap-2"
        @submit.prevent="createInvite.mutate()"
      >
        <Input
          v-model="inviteForm.email"
          type="email"
          class="max-w-xs"
          :placeholder="t('auth.fields.email')"
          required
        />
        <select
          v-model="inviteForm.role"
          class="rounded-md border border-input bg-background px-2"
        >
          <option
            v-for="role in roles.data.value ?? []"
            :key="role.key"
            :value="role.key"
          >
            {{ role.name }}
          </option>
        </select>
        <Button
          variant="primary"
          type="submit"
          :loading="createInvite.isPending.value"
        >
          {{ t('users.invites.send') }}
        </Button>
      </form>
      <ul class="divide-y text-sm">
        <li
          v-for="invite in invites.data.value?.data ?? []"
          :key="invite.id"
          class="flex items-center justify-between py-2"
        >
          <span>
            {{ invite.email }}
            <span class="ml-2 text-xs text-muted"
              >{{ invite.role }} · {{ t(`users.invites.status.${invite.status}`) }}</span
            >
          </span>
          <Button
            v-if="canInvite && invite.status === 'pending'"
            variant="ghost"
            size="sm"
            @click="revokeInvite.mutate(invite.id)"
          >
            {{ t('actions.revoke') }}
          </Button>
        </li>
      </ul>
    </Card>

    <PasswordConfirmDialog
      v-model:open="stepUp.confirmOpen.value"
      @confirmed="stepUp.onConfirmed"
    />
  </div>
</template>
