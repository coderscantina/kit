<script setup lang="ts">
import { keepPreviousData, useQuery, useQueryClient } from '@tanstack/vue-query'
import { toast } from 'vue-sonner'

import { api } from '~/api'
import type { PeopleSegment } from '~/api/resources/people'
import Icon from '~/components/Icon.vue'
import PasswordConfirmDialog from '~/components/PasswordConfirmDialog.vue'
import InviteDialog from '~/components/people/InviteDialog.vue'
import PeopleTable from '~/components/people/PeopleTable.vue'
import { Badge } from '~/components/ui/badge'
import { Button } from '~/components/ui/button'
import { Card } from '~/components/ui/card'
import { Input } from '~/components/ui/input'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '~/components/ui/select'
import TablePaginationFooter from '~/components/ui/TablePaginationFooter.vue'
import { Tabs, TabsList, TabsTrigger } from '~/components/ui/tabs'
import { useAuth } from '~/composables/useAuth'
import { useConfirm } from '~/composables/useConfirm'
import { useStepUp } from '~/composables/useStepUp'
import { useTableQueryState } from '~/composables/useTableQueryState'
import { hasAbilityRequirement } from '~/lib/access-control'
import { queryKeys } from '~/lib/query-keys'
import { toastError } from '~/lib/toast-error'
import { useI18n } from '~/plugins/i18n'

/**
 * One surface for everyone the installation knows: the accounts that exist and
 * the invitations that have not been answered yet. The merge happens on the
 * server, so a page of this list is a page of the merged list.
 */
const { t } = useI18n()
const auth = useAuth()
const queryClient = useQueryClient()
const stepUp = useStepUp()
const { confirm } = useConfirm()

const ALL_ROLES = 'all'

// Page, per-page, sort and search live in the URL, so a filtered list can be
// linked, reloaded and walked back through with the browser's own buttons.
const table = useTableQueryState({ defaultSort: { column: 'name', direction: 'asc' } })

const route = useRoute()
const router = useRouter()

/** Segment and role filter join the rest of the state in the URL. */
const urlParam = (name: string, fallback: string): string => {
  const value = route.query[name]
  const first = Array.isArray(value) ? value[0] : value

  return typeof first === 'string' && first.length > 0 ? first : fallback
}

const writeParam = (name: string, value: string, fallback: string) => {
  const query = { ...route.query }
  if (value === fallback) delete query[name]
  else query[name] = value
  // A narrower list can put the current page past the end, so it starts over.
  delete query.page
  void router.push({ query })
}

const segment = computed<PeopleSegment>({
  get: () => urlParam('status', 'all') as PeopleSegment,
  set: (value) => writeParam('status', value, 'all'),
})

const roleFilter = computed<string>({
  get: () => urlParam('role', ALL_ROLES),
  set: (value) => writeParam('role', value, ALL_ROLES),
})

const listQuery = computed(() => ({
  page: table.params.value.page,
  perPage: table.params.value.perPage,
  search: table.params.value.search,
  role: roleFilter.value === ALL_ROLES ? '' : roleFilter.value,
  status: segment.value,
  sort: table.params.value.sort.column,
  direction: table.params.value.sort.direction,
}))

const people = useQuery({
  queryKey: computed(() => queryKeys.people(listQuery.value)),
  queryFn: () => api.people.index(listQuery.value),
  placeholderData: keepPreviousData,
})

const roles = useQuery({ queryKey: queryKeys.roles(), queryFn: () => api.users.roles() })

const canInvite = computed(() => hasAbilityRequirement(auth.me.value, 'invites.manage'))

const rows = computed(() => people.data.value?.data ?? [])
const counts = computed(() => people.data.value?.counts ?? { active: 0, pending: 0, total: 0 })
const isFiltered = computed(
  () => table.params.value.search !== '' || roleFilter.value !== ALL_ROLES
)

const inviteOpen = ref(false)

const refresh = () => queryClient.invalidateQueries({ queryKey: ['people'] })

const assignRole = async (person: App.Data.PersonData, role: string) => {
  try {
    await stepUp.run(() => api.users.updateRole(person.id, role))
    await refresh()
    toast.success(t('users.roleChanged'))
  } catch (error) {
    toastError(t, 'users.error', error)
  }
}

const remove = async (person: App.Data.PersonData) => {
  const invite = person.kind === 'invite'

  const confirmed = await confirm({
    title: invite ? t('users.invites.revokeTitle') : t('users.removeTitle'),
    message: invite
      ? t('users.invites.revokeConfirm', { email: person.email })
      : t('users.removeConfirm', { name: person.name ?? person.email }),
    confirmLabel: invite ? t('actions.revoke') : t('actions.remove'),
    variant: 'destructive',
  })

  if (!confirmed) return

  try {
    // Revoking an invitation is not a step-up action; removing an account is.
    if (invite) await api.invites.destroy(person.id)
    else await stepUp.run(() => api.users.destroy(person.id))

    await refresh()
    toast.success(invite ? t('users.invites.revoked') : t('users.removed'))
  } catch (error) {
    toastError(t, 'users.error', error)
  }
}

const resend = async (person: App.Data.PersonData) => {
  try {
    await api.invites.resend(person.id)
    await refresh()
    toast.success(t('users.invites.resent', { email: person.email }))
  } catch (error) {
    toastError(t, 'users.invites.error', error)
  }
}

const onInvited = async (email: string) => {
  await refresh()
  toast.success(t('users.invites.sent', { email }))
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-2xl font-semibold">{{ t('users.title') }}</h1>
      <Button
        v-if="canInvite"
        variant="primary"
        @click="inviteOpen = true"
      >
        <Icon name="lucide:user-plus" />
        {{ t('users.invites.send') }}
      </Button>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3">
      <Tabs v-model="segment">
        <TabsList>
          <TabsTrigger value="all">
            {{ t('users.segments.all') }}
            <Badge
              variant="secondary"
              size="xs"
              >{{ counts.total }}</Badge
            >
          </TabsTrigger>
          <TabsTrigger value="active">
            {{ t('users.segments.active') }}
            <Badge
              variant="secondary"
              size="xs"
              >{{ counts.active }}</Badge
            >
          </TabsTrigger>
          <TabsTrigger value="pending">
            {{ t('users.segments.pending') }}
            <Badge
              variant="secondary"
              size="xs"
              >{{ counts.pending }}</Badge
            >
          </TabsTrigger>
        </TabsList>
      </Tabs>

      <div class="flex items-center gap-2">
        <Select v-model="roleFilter">
          <SelectTrigger class="w-40">
            <SelectValue>
              {{
                roleFilter === ALL_ROLES
                  ? t('users.allRoles')
                  : (roles.data.value?.find((role) => role.key === roleFilter)?.name ?? roleFilter)
              }}
            </SelectValue>
          </SelectTrigger>
          <SelectContent>
            <SelectItem :value="ALL_ROLES">{{ t('users.allRoles') }}</SelectItem>
            <SelectItem
              v-for="role in roles.data.value ?? []"
              :key="role.key"
              :value="role.key"
            >
              {{ role.name }}
            </SelectItem>
          </SelectContent>
        </Select>
        <Input
          v-model="table.search.value"
          type="search"
          class="w-64"
          :placeholder="t('users.searchPlaceholder')"
        />
      </div>
    </div>

    <Card class="overflow-hidden">
      <PeopleTable
        v-model:sort="table.sort.value"
        :rows="rows"
        :roles="roles.data.value ?? []"
        :loading="people.isPending.value"
        :refetching="people.isFetching.value && Boolean(people.data.value)"
        :filtered="isFiltered"
        :segment="segment"
        @assign-role="assignRole"
        @remove="remove"
        @resend="resend"
      />
      <TablePaginationFooter
        v-if="people.data.value"
        v-model:current-page="table.page.value"
        v-model:per-page="table.perPage.value"
        :meta="people.data.value"
        :page-size-options="[10, 20, 50, 100]"
      />
    </Card>

    <InviteDialog
      v-model:open="inviteOpen"
      :roles="roles.data.value ?? []"
      @invited="onInvited"
    />

    <PasswordConfirmDialog
      v-model:open="stepUp.confirmOpen.value"
      @confirmed="stepUp.onConfirmed"
    />
  </div>
</template>
