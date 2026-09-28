<script setup lang="ts">
import { toast } from 'vue-sonner'

import { api } from '~/api'
import Icon from '~/components/Icon.vue'
import {
  Attachment,
  AttachmentAction,
  AttachmentActions,
  AttachmentContent,
  AttachmentDescription,
  AttachmentMedia,
  AttachmentTitle,
  AttachmentTrigger,
} from '~/components/ui/attachment'
import FileDropZone from '~/components/ui/FileDropZone.vue'
import { Progress } from '~/components/ui/progress'
import { Skeleton } from '~/components/ui/skeleton'
import { useConfirm } from '~/composables/useConfirm'
import { useFormat } from '~/composables/useFormat'
import { useReactiveQuery } from '~/lib/reactive'
import { errorMessage, toastError } from '~/lib/toast-error'
import { useI18n } from '~/plugins/i18n'

/**
 * The files on one record, live, with uploads when the viewer may change
 * the record. The model needs HasAttachments; the server decides who may
 * do what, `editable` only decides what to offer. A finished upload leaves
 * the pending list and appears through the query, so every open screen
 * shows it the same way.
 */
const props = defineProps<{ type: string; id: string; editable?: boolean }>()

const { t } = useI18n()
const { bytes, relative } = useFormat()
const { confirm } = useConfirm()

const files = useReactiveQuery('attachments.list', () => ({ type: props.type, id: props.id }))

interface PendingUpload {
  key: number
  name: string
  progress: number
  error: string | null
}

const pending = ref<PendingUpload[]>([])
let nextKey = 0

const upload = async (file: File) => {
  const entry = reactive<PendingUpload>({
    key: nextKey++,
    name: file.name,
    progress: 0,
    error: null,
  })
  pending.value.push(entry)

  try {
    await api.attachments.upload({ type: props.type, id: props.id }, file, {
      onProgress: (fraction) => {
        entry.progress = Math.round(fraction * 100)
      },
    })
    dismiss(entry.key)
  } catch (error) {
    entry.error = errorMessage(error)
  }
}

const dismiss = (key: number) => {
  pending.value = pending.value.filter((entry) => entry.key !== key)
}

const onSelect = (selected: File[]) => {
  for (const file of selected) void upload(file)
}

const remove = async (file: App.Data.AttachmentData) => {
  const confirmed = await confirm({
    title: t('records.attachments.removeTitle'),
    message: t('records.attachments.removeConfirm', { name: file.name }),
    confirmLabel: t('actions.remove'),
    variant: 'destructive',
  })
  if (!confirmed) return

  try {
    await api.attachments.remove(file.id)
    toast.success(t('records.attachments.removed'))
  } catch (error) {
    toastError(t, 'records.attachments.error', error)
  }
}

const icon = (mimeType: string): string => {
  if (mimeType.startsWith('image/')) return 'lucide:image'
  if (mimeType === 'application/pdf') return 'lucide:file-text'
  if (/sheet|excel|csv/.test(mimeType)) return 'lucide:file-spreadsheet'
  if (/zip|compressed/.test(mimeType)) return 'lucide:file-archive'
  return 'lucide:file'
}
</script>

<template>
  <div class="grid gap-3">
    <FileDropZone
      v-if="editable"
      multiple
      :hint="t('records.attachments.hint')"
      @select="onSelect"
      @error="(message) => toast.error(message)"
    />

    <div
      v-if="files.isPending.value"
      class="flex gap-3"
    >
      <Skeleton class="h-32 w-30 rounded-xl" />
      <Skeleton class="h-32 w-30 rounded-xl" />
    </div>

    <p
      v-else-if="(files.data.value ?? []).length === 0 && pending.length === 0"
      class="rounded-xl border border-dashed border-border py-6 text-center text-sm text-muted"
    >
      {{ t('records.attachments.empty') }}
    </p>

    <ul
      v-else
      class="flex flex-wrap gap-3"
      :aria-label="t('records.attachments.title')"
    >
      <li
        v-for="entry in pending"
        :key="`pending-${entry.key}`"
      >
        <Attachment
          orientation="vertical"
          :state="entry.error ? 'error' : 'uploading'"
        >
          <AttachmentMedia>
            <Icon
              :name="entry.error ? 'lucide:circle-alert' : 'lucide:upload'"
              aria-hidden="true"
            />
          </AttachmentMedia>
          <AttachmentContent>
            <AttachmentTitle>{{ entry.name }}</AttachmentTitle>
            <AttachmentDescription
              v-if="entry.error"
              :title="entry.error"
            >
              {{ entry.error }}
            </AttachmentDescription>
            <Progress
              v-else
              :model-value="entry.progress"
              class="mt-1.5 h-1"
              :aria-label="t('records.attachments.uploading', { name: entry.name })"
            />
          </AttachmentContent>
          <AttachmentActions v-if="entry.error">
            <AttachmentAction
              :aria-label="t('actions.close')"
              @click="dismiss(entry.key)"
            >
              <Icon
                name="lucide:x"
                aria-hidden="true"
              />
            </AttachmentAction>
          </AttachmentActions>
        </Attachment>
      </li>

      <li
        v-for="file in files.data.value ?? []"
        :key="file.id"
      >
        <Attachment orientation="vertical">
          <AttachmentTrigger
            as="a"
            :href="file.url"
            target="_blank"
            rel="noopener"
            :aria-label="t('records.attachments.open', { name: file.name })"
          />
          <AttachmentMedia :variant="file.thumbnailUrl ? 'image' : 'icon'">
            <img
              v-if="file.thumbnailUrl"
              :src="file.thumbnailUrl"
              alt=""
              loading="lazy"
            />
            <Icon
              v-else
              :name="icon(file.mimeType)"
              aria-hidden="true"
            />
          </AttachmentMedia>
          <AttachmentContent>
            <AttachmentTitle :title="file.name">{{ file.name }}</AttachmentTitle>
            <AttachmentDescription>
              {{ bytes(file.size) }} · {{ relative(file.createdAt) }}
            </AttachmentDescription>
          </AttachmentContent>
          <AttachmentActions v-if="editable">
            <AttachmentAction
              :aria-label="t('records.attachments.remove', { name: file.name })"
              @click="remove(file)"
            >
              <Icon
                name="lucide:trash-2"
                aria-hidden="true"
              />
            </AttachmentAction>
          </AttachmentActions>
        </Attachment>
      </li>
    </ul>
  </div>
</template>
