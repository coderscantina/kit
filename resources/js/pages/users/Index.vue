<script setup lang="ts">
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { toast } from 'vue-sonner'

import { api } from '~/api'
import PasswordConfirmDialog from '~/components/PasswordConfirmDialog.vue'
import { Button } from '~/components/ui/button'
import { Card } from '~/components/ui/card'
import { Input } from '~/components/ui/input'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
  TableSortableHead,
} from '~/components/ui/table'
import TableEmptyRow from '~/components/ui/TableEmptyRow.vue'
import TableLoadingRow from '~/components/ui/TableLoadingRow.vue'
import TablePaginationFooter from '~/components/ui/TablePaginationFooter.vue'
import { useAuth } from '~/composables/useAuth'
import { useConfirm } from '~/composables/useConfirm'
import { useFormat } from '~/composables/useFormat'
import { useStepUp } from '~/composables/useStepUp'
import { useTableQueryState } from '~/composables/useTableQueryState'
import { hasAbilityRequirement } from '~/lib/access-control'
import { queryKeys } from '~/lib/query-keys'
import { toastError } from '~/lib/toast-error'
import { useI18n } from '~/plugins/i18n'

const { t } = useI18n()
const auth = useAuth()
const queryClient = useQueryClient()
const stepUp = useStepUp()
const { confirm } = useConfirm()
const { date, relative } = useFormat()

// Page, per-page, sort and search live in the URL, so a filtered list can be
// linked, reloaded and walked back through with the browser's own buttons.
const table = useTableQueryState({ defaultSort: { column: 'name', direction: 'asc' } })

const listQuery = computed(() => ({
  page: table.params.value.page,
  perPage: table.params.value.perPage,
  search: table.params.value.search,
  sort: table.params.value.sort.column,
  direction: table.params.value.sort.direction,
}))

const users = useQuery({
  queryKey: computed(() => queryKeys.users(listQuery.value)),
  queryFn: () => api.users.index(listQuery.value),
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

const rows = computed(() => users.data.value?.data ?? [])

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
  const confirmed = await confirm({
    title: t('users.removeTitle'),
    message: t('users.removeConfirm', { name: user.name }),
    confirmLabel: t('actions.remove'),
    variant: 'destructive',
  })

  if (!confirmed) return

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

    <div class="flex items-center gap-2">
      <Input
        v-model="table.search.value"
        type="search"
        class="max-w-xs"
        :placeholder="t('users.searchPlaceholder')"
      />
    </div>

    <Card class="overflow-hidden">
      <Table>
        <TableHeader>
          <TableRow>
            <TableSortableHead
              v-model="table.sort.value"
              column="name"
            >
              {{ t('auth.fields.name') }}
            </TableSortableHead>
            <TableSortableHead
              v-model="table.sort.value"
              column="email"
            >
              {{ t('auth.fields.email') }}
            </TableSortableHead>
            <TableHead>{{ t('users.role') }}</TableHead>
            <TableSortableHead
              v-model="table.sort.value"
              column="last_login_at"
            >
              {{ t('users.lastLogin') }}
            </TableSortableHead>
            <TableSortableHead
              v-model="table.sort.value"
              column="created_at"
            >
              {{ t('users.created') }}
            </TableSortableHead>
            <TableHead />
          </TableRow>
        </TableHeader>
        <!-- Dim while refetching with data present; skeleton rows only when there is nothing yet. -->
        <TableBody
          :class="[
            'transition-opacity',
            users.isFetching.value && users.data.value ? 'opacity-50' : '',
          ]"
        >
          <TableLoadingRow
            v-if="users.isPending.value"
            :colspan="6"
            :rows="5"
          />
          <TableEmptyRow
            v-else-if="rows.length === 0"
            :colspan="6"
          />
          <TableRow
            v-for="user in rows"
            :key="user.id"
          >
            <TableCell>{{ user.name }}</TableCell>
            <TableCell>{{ user.email }}</TableCell>
            <TableCell>
              <select
                v-if="canManageRoles && user.id !== auth.user.value?.id"
                :value="user.role ?? ''"
                class="rounded-md border border-input-border bg-input px-2 py-1"
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
            </TableCell>
            <TableCell class="text-muted">
              <span :title="user.lastLoginAt ? date(user.lastLoginAt) : undefined">
                {{ user.lastLoginAt ? relative(user.lastLoginAt) : t('users.neverLoggedIn') }}
              </span>
            </TableCell>
            <TableCell class="text-muted">{{ date(user.createdAt) }}</TableCell>
            <TableCell class="text-right">
              <Button
                v-if="canManageUsers && user.id !== auth.user.value?.id"
                variant="ghost"
                size="sm"
                @click="removeUser(user)"
              >
                {{ t('actions.remove') }}
              </Button>
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
      <TablePaginationFooter
        v-if="users.data.value"
        v-model:current-page="table.page.value"
        v-model:per-page="table.perPage.value"
        :meta="users.data.value"
        :page-size-options="[10, 20, 50, 100]"
      />
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
          class="rounded-md border border-input-border bg-input px-2"
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
