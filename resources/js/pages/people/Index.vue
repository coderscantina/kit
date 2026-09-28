<script setup lang="ts">
import { useQuery } from '@tanstack/vue-query'
import { computed, ref } from 'vue'
import { toast } from 'vue-sonner'

import { api } from '~/api'
import type { PeopleSegment } from '~/api/resources/people'
import Icon from '~/components/Icon.vue'
import PasswordConfirmDialog from '~/components/PasswordConfirmDialog.vue'
import InviteDialog from '~/components/people/InviteDialog.vue'
import PeopleTable from '~/components/people/PeopleTable.vue'
import RecordHistory from '~/components/records/RecordHistory.vue'
import { AvatarList } from '~/components/ui/avatar'
import { Button } from '~/components/ui/button'
import {
  SavedViews,
  TableExport,
  TableFilter,
  TableSegments,
  TableToolbar,
  type ExportFormat,
  type FilterField,
  type TableSegment,
} from '~/components/ui/data-table'
import { Sheet, SheetContent, SheetHeaderCombined } from '~/components/ui/sheet'
import TablePaginationFooter from '~/components/ui/TablePaginationFooter.vue'
import { useAuth } from '~/composables/useAuth'
import { useConfirm } from '~/composables/useConfirm'
import { usePresenceStatus } from '~/composables/usePresenceStatus'
import { useSavedViews } from '~/composables/useSavedViews'
import { useStepUp } from '~/composables/useStepUp'
import { useTableQueryState } from '~/composables/useTableQueryState'
import { hasAbilityRequirement } from '~/lib/access-control'
import { queryKeys } from '~/lib/query-keys'
import { useReactiveQuery } from '~/lib/reactive'
import { toastError } from '~/lib/toast-error'
import { useI18n } from '~/plugins/i18n'

/**
 * One surface for everyone the installation knows: the accounts that exist and
 * the invitations that have not been answered yet. The merge happens on the
 * server, so a page of this list is a page of the merged list.
 *
 * Page, page size, sort, search and every filter chip live in the URL, so a
 * narrowed list can be linked, reloaded, walked back through with the
 * browser's own buttons — and saved under a name.
 */
const { t } = useI18n()
const auth = useAuth()
const stepUp = useStepUp()
const { confirm } = useConfirm()

const table = useTableQueryState({
  defaultSort: { column: 'name', direction: 'asc' },
  filterKeys: ['status', 'role', 'email', 'created_at'],
})

/** Everyone else with this page open, and who is online in the rows below. */
const { others, statusOf } = usePresenceStatus('users')

const viewers = computed(() =>
  others.value.map(({ id, name, avatarUrl, color }) => ({ id, name, avatar: avatarUrl, color }))
)

const views = useSavedViews({ scope: 'people', table })

const viewerId = computed(() => auth.user.value?.id ?? '')

/** Live: an account or invitation that changes anywhere lands here without a reload. */
const people = useReactiveQuery(
  'people.list',
  () => ({ viewerId: viewerId.value, params: table.params.value }),
  { enabled: computed(() => viewerId.value !== ''), list: true }
)

const roles = useQuery({ queryKey: queryKeys.roles(), queryFn: () => api.users.roles() })

/** The segment picker writes the same `status` parameter the filters do. */
const segment = computed<PeopleSegment>({
  get: () => (table.filters.value.status as PeopleSegment | undefined) ?? 'all',
  set: (value) => {
    const next = { ...table.filters.value }
    if (value === 'all') delete next.status
    else next.status = value

    table.filters.value = next
  },
})

const filterFields = computed<FilterField[]>(() => [
  {
    id: 'role',
    label: t('users.role'),
    type: 'select',
    options: (roles.data.value ?? []).map((role) => ({ value: role.key, label: role.name })),
  },
  { id: 'email', label: t('auth.fields.email'), type: 'text' },
  { id: 'created_at', label: t('users.joined'), type: 'date' },
])

const canInvite = computed(() => hasAbilityRequirement(auth.me.value, 'invites.manage'))

/**
 * The same parameters the table is showing. The resource drops `page` and
 * `per_page`, so the file is the whole filtered list rather than this page.
 */
const exportPeople = (format: ExportFormat) => api.people.export(table.params.value, format)

const rows = computed(() => people.data.value?.data ?? [])
const counts = computed(() => people.data.value?.counts ?? { active: 0, pending: 0, total: 0 })

const segments = computed<TableSegment<PeopleSegment>[]>(() => [
  { value: 'all', label: t('users.segments.all'), count: counts.value.total },
  { value: 'active', label: t('users.segments.active'), count: counts.value.active },
  { value: 'pending', label: t('users.segments.pending'), count: counts.value.pending },
])

const inviteOpen = ref(false)

/** The account whose history the sheet shows; the sheet is open while it is set. */
const historyOf = ref<App.Data.PersonData | null>(null)
const historyOpen = computed({
  get: () => historyOf.value !== null,
  set: (open: boolean) => {
    if (!open) historyOf.value = null
  },
})

/** The push does this too; the refetch is for installs running without realtime. */
const refresh = () => people.refetch()

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
  <div class="grid gap-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-2xl font-semibold text-primary">{{ t('users.title') }}</h1>
      <AvatarList
        v-if="viewers.length > 0"
        class="ml-auto"
        :users="viewers"
        :max="5"
      />
      <Button
        v-if="canInvite"
        variant="accent"
        @click="inviteOpen = true"
      >
        <Icon name="lucide:user-plus" />
        {{ t('users.invites.send') }}
      </Button>
    </div>

    <TableToolbar>
      <template #lead>
        <TableSegments
          v-model="segment"
          :segments="segments"
        />
      </template>

      <TableFilter
        v-model="table.filters.value"
        v-model:search="table.search.value"
        :fields="filterFields"
        :placeholder="t('users.searchPlaceholder')"
      />

      <template #actions>
        <SavedViews
          :views="views"
          :dirty="table.isFiltered.value || Object.keys(table.snapshot.value).length > 0"
        />
        <TableExport :download="exportPeople" />
      </template>
    </TableToolbar>

    <div class="grid gap-3">
      <PeopleTable
        v-model:sort="table.sort.value"
        :rows="rows"
        :status-of="statusOf"
        :roles="roles.data.value ?? []"
        :loading="people.isPending.value"
        :refetching="people.isFetching.value && Boolean(people.data.value)"
        :filtered="table.isFiltered.value"
        :segment="segment"
        @assign-role="assignRole"
        @remove="remove"
        @resend="resend"
        @history="(person) => (historyOf = person)"
      />
      <TablePaginationFooter
        v-if="people.data.value"
        v-model:current-page="table.page.value"
        v-model:per-page="table.perPage.value"
        :meta="people.data.value"
        :page-size-options="[10, 20, 50, 100]"
      />
    </div>

    <InviteDialog
      v-model:open="inviteOpen"
      :roles="roles.data.value ?? []"
      @invited="onInvited"
    />

    <Sheet v-model:open="historyOpen">
      <SheetContent class="grid w-full content-start overflow-y-auto p-5 sm:max-w-lg">
        <SheetHeaderCombined
          :title="t('records.history.title')"
          :description="historyOf?.name ?? historyOf?.email"
        />
        <RecordHistory
          v-if="historyOf"
          type="users"
          :id="historyOf.id"
        />
      </SheetContent>
    </Sheet>

    <PasswordConfirmDialog
      v-model:open="stepUp.confirmOpen.value"
      @confirmed="stepUp.onConfirmed"
    />
  </div>
</template>
