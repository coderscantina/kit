<script setup lang="ts">
import { ref } from 'vue'

import { Alert } from '~/components/ui/alert'
import { Button } from '~/components/ui/button'
import { Card } from '~/components/ui/card'
import { Input } from '~/components/ui/input'
import { Label } from '~/components/ui/label'
import { useAiStream } from '~/composables/useAiStream'
import { useI18n } from '~/plugins/i18n'

const { t } = useI18n()

const question = ref('')
const assistant = useAiStream('assistant.ask')

const ask = async (): Promise<void> => {
  const asked = question.value.trim()
  if (!asked || assistant.isStreaming.value) return

  await assistant.run({ question: asked })
}
</script>

<template>
  <div class="space-y-6">
    <h1 class="text-2xl font-semibold">{{ t('ai.title') }}</h1>

    <Card class="space-y-4 p-6">
      <form
        class="flex gap-2"
        @submit.prevent="ask"
      >
        <div class="flex-1 space-y-2">
          <Label for="ai-question">{{ t('ai.label') }}</Label>
          <Input
            id="ai-question"
            v-model="question"
            :placeholder="t('ai.placeholder')"
            :disabled="assistant.isStreaming.value"
          />
        </div>
        <Button
          variant="primary"
          type="submit"
          class="self-end"
          :loading="assistant.isStreaming.value"
          :disabled="!question.trim()"
        >
          {{ t('ai.ask') }}
        </Button>
      </form>

      <Button
        v-if="assistant.isStreaming.value"
        variant="ghost"
        size="sm"
        @click="assistant.cancel()"
      >
        {{ t('ai.stop') }}
      </Button>

      <p
        v-if="assistant.status.value"
        class="text-sm text-muted"
      >
        {{ assistant.status.value }}
      </p>

      <Alert
        v-if="assistant.error.value"
        color="destructive"
      >
        {{ assistant.error.value }}
      </Alert>

      <p
        v-if="assistant.content.value"
        class="whitespace-pre-wrap text-sm"
      >
        {{ assistant.content.value }}
      </p>
      <p
        v-else-if="!assistant.isStreaming.value && !assistant.error.value"
        class="text-sm text-muted"
      >
        {{ t('ai.empty') }}
      </p>
    </Card>
  </div>
</template>
