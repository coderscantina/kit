<script setup lang="ts">
import { api } from '~/api'
import Icon from '~/components/Icon.vue'
import { Avatar } from '~/components/ui/avatar'
import { Button } from '~/components/ui/button'
import { useI18n } from '~/plugins/i18n'

/**
 * Picks an image, squares it and resizes it in the browser, then uploads the
 * result. Cropping here rather than on the server keeps an image library off
 * the backend and means the bytes crossing the wire are already the bytes we
 * intend to keep. The server still validates what arrives.
 */
const props = defineProps<{ name: string; avatarUrl: string | null }>()
const emit = defineEmits<{ changed: [user: App.Data.UserData] }>()

const { t } = useI18n()

/** Big enough for a retina 96px avatar, small enough to stay well under the size cap. */
const EDGE = 512

const input = ref<HTMLInputElement | null>(null)
const busy = ref(false)
const error = ref<string | null>(null)
const dragging = ref(false)

/** Centre crop to a square, then scale to EDGE. Losing the edges beats squashing the face. */
const square = (file: File): Promise<Blob> =>
  new Promise((resolve, reject) => {
    const url = URL.createObjectURL(file)
    const image = new Image()

    image.onload = () => {
      URL.revokeObjectURL(url)

      const edge = Math.min(image.width, image.height)
      const canvas = document.createElement('canvas')
      canvas.width = EDGE
      canvas.height = EDGE

      const context = canvas.getContext('2d')
      if (!context) return reject(new Error('canvas unavailable'))

      context.drawImage(
        image,
        (image.width - edge) / 2,
        (image.height - edge) / 2,
        edge,
        edge,
        0,
        0,
        EDGE,
        EDGE
      )

      canvas.toBlob(
        (blob) => (blob ? resolve(blob) : reject(new Error('encoding failed'))),
        'image/png'
      )
    }

    image.onerror = () => {
      URL.revokeObjectURL(url)
      reject(new Error('unreadable image'))
    }

    image.src = url
  })

const upload = async (file: File | undefined) => {
  if (!file) return

  busy.value = true
  error.value = null
  try {
    emit('changed', await api.account.uploadAvatar(await square(file)))
  } catch {
    error.value = t('account.profile.avatarFailed')
  } finally {
    busy.value = false
    if (input.value) input.value.value = ''
  }
}

const remove = async () => {
  busy.value = true
  error.value = null
  try {
    emit('changed', await api.account.removeAvatar())
  } catch {
    error.value = t('account.profile.avatarFailed')
  } finally {
    busy.value = false
  }
}

const onDrop = (event: DragEvent) => {
  dragging.value = false
  void upload(event.dataTransfer?.files?.[0])
}

const pick = () => input.value?.click()
</script>

<template>
  <div class="flex items-center gap-4">
    <button
      type="button"
      :class="[
        'rounded-2xl ring-offset-surface transition focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
        dragging ? 'ring-2 ring-ring' : '',
      ]"
      :aria-label="t('account.profile.avatarChange')"
      :disabled="busy"
      @click="pick"
      @dragover.prevent="dragging = true"
      @dragleave="dragging = false"
      @drop.prevent="onDrop"
    >
      <Avatar
        :name="props.name"
        :avatar="props.avatarUrl"
        size="lg"
      />
    </button>

    <div class="grid gap-1.5">
      <div class="flex gap-2">
        <Button
          type="button"
          variant="default"
          size="sm"
          :loading="busy"
          @click="pick"
        >
          <Icon name="lucide:upload" />
          {{ t('account.profile.avatarChange') }}
        </Button>
        <Button
          v-if="props.avatarUrl"
          type="button"
          variant="ghost"
          size="sm"
          :disabled="busy"
          @click="remove"
        >
          {{ t('actions.remove') }}
        </Button>
      </div>
      <p class="text-xs text-muted">{{ t('account.profile.avatarHint') }}</p>
      <p
        v-if="error"
        class="text-xs text-destructive"
      >
        {{ error }}
      </p>
    </div>

    <input
      ref="input"
      type="file"
      accept="image/png,image/jpeg,image/webp"
      class="hidden"
      @change="upload(($event.target as HTMLInputElement).files?.[0])"
    />
  </div>
</template>
