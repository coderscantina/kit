<script setup lang="ts">
import { ref } from 'vue'
import { toast } from 'vue-sonner'

import type { DownloadedFile } from '~/api/client'
import Icon from '~/components/Icon.vue'
import { Button } from '~/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuTrigger,
} from '~/components/ui/dropdown-menu'
import { Spinner } from '~/components/ui/spinner'
import { SimpleTooltip } from '~/components/ui/tooltip'
import { saveFile } from '~/lib/download'
import { toastError } from '~/lib/toast-error'
import { useI18n } from '~/plugins/i18n'

import { EXPORT_FORMAT_ICONS, EXPORT_FORMATS, type ExportFormat } from './types'

/**
 * The current list as a file. The caller supplies the request; this owns the
 * menu, the busy state and what happens to the bytes.
 *
 * The download is fetched rather than linked, so a 403 or a rate limit is an
 * error toast instead of an error page in a new tab, and so the trigger can
 * say that something is happening while a large list is being written.
 */
const props = withDefaults(
  defineProps<{
    /** Runs the request. Dropping `page`/`per_page` is the resource's job. */
    download: (format: ExportFormat) => Promise<DownloadedFile>
    formats?: readonly ExportFormat[]
    disabled?: boolean
  }>(),
  {
    formats: () => EXPORT_FORMATS,
    disabled: false,
  }
)

const { t } = useI18n()

const busy = ref(false)

const run = async (format: ExportFormat) => {
  if (busy.value) return

  busy.value = true

  try {
    const file = await props.download(format)
    saveFile(file)

    if (file.truncated) toast.warning(t('exports.truncated'))
  } catch (error) {
    toastError(t, 'exports.error', error)
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <DropdownMenu>
    <DropdownMenuTrigger as-child>
      <SimpleTooltip
        :tooltip="t('exports.trigger')"
        as-child
      >
        <Button
          variant="outline"
          size="icon"
          class="max-sm:size-10"
          :disabled="disabled || busy"
          :aria-label="busy ? t('exports.busy') : t('exports.trigger')"
          :aria-busy="busy"
        >
          <Spinner
            v-if="busy"
            class="size-4"
          />
          <Icon
            v-else
            name="lucide:download"
            aria-hidden="true"
          />
        </Button>
      </SimpleTooltip>
    </DropdownMenuTrigger>

    <DropdownMenuContent
      align="end"
      class="w-52"
    >
      <DropdownMenuLabel>{{ t('exports.title') }}</DropdownMenuLabel>

      <DropdownMenuItem
        v-for="format in formats"
        :key="format"
        @select="run(format)"
      >
        <Icon
          :name="EXPORT_FORMAT_ICONS[format]"
          size="14"
          class="text-muted"
          aria-hidden="true"
        />
        {{ t(`exports.formats.${format}`) }}
      </DropdownMenuItem>
    </DropdownMenuContent>
  </DropdownMenu>
</template>
