<script setup lang="ts">
import { UserAvatar } from '~/components/ui/avatar'
import type { FieldEditor } from '~/composables/useFieldPresence'
import { useI18n } from '~/plugins/i18n'

const props = defineProps<{ editors: FieldEditor[] }>()

const { t } = useI18n()

/**
 * Unavailable, not online, once they have changes in it: red is the state
 * that says do not save over this yet. Clean and focused is only company.
 */
const label = (editor: FieldEditor): string =>
  t(editor.dirty ? 'presence.field.changing' : 'presence.field.editing', {
    name: editor.member.name,
  })
</script>

<template>
  <div
    v-if="props.editors.length > 0"
    class="flex -space-x-1"
  >
    <UserAvatar
      v-for="editor in props.editors"
      :key="editor.member.id"
      size="sm"
      :name="editor.member.name"
      :avatar="editor.member.avatarUrl"
      :border-color="editor.member.color"
      :status="editor.dirty ? 'unavailable' : 'online'"
      :status-label="label(editor)"
      :title="label(editor)"
      class="ring-2 ring-background"
    />
  </div>
</template>
