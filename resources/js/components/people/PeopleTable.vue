<script setup lang="ts">
import type { PeopleSegment } from '~/api/resources/people'
import Icon from '~/components/Icon.vue'
import { Avatar } from '~/components/ui/avatar'
import { Badge } from '~/components/ui/badge'
import { Button } from '~/components/ui/button'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '~/components/ui/select'
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
import { SimpleTooltip } from '~/components/ui/tooltip'
import { useFormat } from '~/composables/useFormat'
import type { TableSort } from '~/composables/useTableQueryState'
import { useI18n } from '~/plugins/i18n'

/**
 * Accounts and outstanding invitations in one table. A row's `kind` decides
 * which actions it has, `state` decides which badge it wears, and the
 * per-row permission flags come from the server, so this component holds no
 * role arithmetic of its own.
 */
const props = defineProps<{
  rows: App.Data.PersonData[]
  roles: App.Data.RoleData[]
  loading: boolean
  refetching: boolean
  /** Empty-state copy changes once a filter is on: nothing found is not nothing here. */
  filtered: boolean
  segment: PeopleSegment
}>()

const emit = defineEmits<{
  assignRole: [person: App.Data.PersonData, role: string]
  remove: [person: App.Data.PersonData]
  resend: [person: App.Data.PersonData]
}>()

const sort = defineModel<TableSort>('sort', { required: true })

const { t } = useI18n()
const { date, dateTime, relative } = useFormat()

const COLUMNS = 5

/** The row whose role cell is currently a select. One at a time. */
const editingRole = ref<string | null>(null)

const roleName = (key: string | null): string =>
  props.roles.find((role) => role.key === key)?.name ?? key ?? '–'

const stateVariant = (state: string) =>
  state === 'active' ? 'success' : state === 'expired' ? 'destructive' : 'warning'

const chooseRole = (person: App.Data.PersonData, role: string) => {
  editingRole.value = null
  if (role !== person.role) emit('assignRole', person, role)
}

const emptyLabel = computed(() => {
  if (props.filtered) return t('users.empty.filtered')

  return props.segment === 'pending' ? t('users.empty.pending') : t('users.empty.all')
})
</script>

<template>
  <Table :label="t('users.title')">
    <TableHeader>
      <TableRow>
        <TableSortableHead
          v-model="sort"
          column="name"
        >
          {{ t('users.person') }}
        </TableSortableHead>
        <TableHead>{{ t('users.role') }}</TableHead>
        <TableHead>{{ t('users.status') }}</TableHead>
        <TableSortableHead
          v-model="sort"
          column="created_at"
        >
          {{ t('users.joined') }}
        </TableSortableHead>
        <TableHead>
          <span class="sr-only">{{ t('users.rowActions') }}</span>
        </TableHead>
      </TableRow>
    </TableHeader>

    <!-- Dim while refetching with rows on screen; skeletons only when there is nothing yet. -->
    <TableBody :class="['transition-opacity', refetching ? 'opacity-50' : '']">
      <TableLoadingRow
        v-if="loading"
        :colspan="COLUMNS"
        :rows="5"
      />
      <TableEmptyRow
        v-else-if="rows.length === 0"
        :colspan="COLUMNS"
        :label="emptyLabel"
      />
      <TableRow
        v-for="person in rows"
        :key="person.id"
      >
        <TableCell>
          <div class="flex items-center gap-3">
            <Avatar
              :name="person.name ?? person.email"
              :avatar="person.avatarUrl"
              :class="person.kind === 'invite' ? 'opacity-60 grayscale' : ''"
            />
            <div class="min-w-0">
              <div class="flex items-center gap-1.5 truncate font-medium">
                {{ person.name ?? person.email }}
                <SimpleTooltip
                  v-if="person.twoFactorEnabled"
                  :tooltip="t('users.twoFactorOn')"
                >
                  <Icon
                    name="lucide:shield-check"
                    class="text-success-foreground"
                  />
                </SimpleTooltip>
              </div>
              <div class="truncate text-xs text-muted">
                {{ person.kind === 'invite' ? t('users.invitedPerson') : person.email }}
              </div>
            </div>
          </div>
        </TableCell>

        <TableCell :label="t('users.role')">
          <Select
            v-if="editingRole === person.id"
            :model-value="person.role ?? ''"
            @update:model-value="chooseRole(person, String($event))"
          >
            <SelectTrigger class="h-8 w-36">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem
                v-for="role in roles"
                :key="role.key"
                :value="role.key"
              >
                {{ role.name }}
              </SelectItem>
            </SelectContent>
          </Select>
          <button
            v-else-if="person.canAssignRole"
            type="button"
            class="cursor-pointer rounded-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            :aria-label="t('users.changeRole')"
            @click="editingRole = person.id"
          >
            <Badge
              variant="secondary"
              size="sm"
              >{{ roleName(person.role) }}</Badge
            >
          </button>
          <Badge
            v-else
            variant="surface"
            size="sm"
            >{{ roleName(person.role) }}</Badge
          >
        </TableCell>

        <TableCell :label="t('users.status')">
          <Badge
            :variant="stateVariant(person.state)"
            size="sm"
          >
            {{ t(`users.states.${person.state}`) }}
          </Badge>
        </TableCell>

        <TableCell
          :label="t('users.joined')"
          class="text-muted"
        >
          <span
            v-if="person.kind === 'invite' && person.state === 'pending'"
            :title="dateTime(person.expiresAt)"
          >
            {{ t('users.expires', { when: relative(person.expiresAt) }) }}
          </span>
          <span
            v-else
            :title="dateTime(person.createdAt)"
          >
            {{ date(person.createdAt) }}
          </span>
        </TableCell>

        <TableCell>
          <div class="flex grow justify-end gap-1">
            <SimpleTooltip
              v-if="person.canResend"
              :tooltip="t('users.invites.resend')"
            >
              <Button
                variant="ghost"
                size="icon"
                class="max-sm:size-10"
                :aria-label="t('users.invites.resend')"
                @click="emit('resend', person)"
              >
                <Icon name="lucide:send" />
              </Button>
            </SimpleTooltip>
            <SimpleTooltip
              v-if="person.canRemove"
              :tooltip="person.kind === 'invite' ? t('actions.revoke') : t('actions.remove')"
            >
              <Button
                variant="ghost"
                size="icon"
                class="text-destructive-foreground max-sm:size-10"
                :aria-label="person.kind === 'invite' ? t('actions.revoke') : t('actions.remove')"
                @click="emit('remove', person)"
              >
                <Icon :name="person.kind === 'invite' ? 'lucide:trash-2' : 'lucide:user-minus'" />
              </Button>
            </SimpleTooltip>
          </div>
        </TableCell>
      </TableRow>
    </TableBody>
  </Table>
</template>
