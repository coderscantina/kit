<script setup lang="ts">
import { computed, ref } from 'vue'

import Icon from '~/components/Icon.vue'
import { Bubble, BubbleContent } from '~/components/ui/bubble'
import {
  InputGroup,
  InputGroupAddon,
  InputGroupButton,
  InputGroupTextarea,
} from '~/components/ui/input-group'
import { Marker, MarkerContent, MarkerIcon } from '~/components/ui/marker'
import { Message, MessageContent, MessageFooter } from '~/components/ui/message'
import {
  MessageScroller,
  MessageScrollerButton,
  MessageScrollerContent,
  MessageScrollerItem,
  MessageScrollerProvider,
  MessageScrollerViewport,
} from '~/components/ui/message-scroller'
import { Spinner } from '~/components/ui/spinner'
import { useAiStream } from '~/composables/useAiStream'
import { useI18n } from '~/plugins/i18n'

interface Turn {
  id: string
  role: 'user' | 'assistant'
  text: string
}

const { t } = useI18n()

const question = ref('')
const turns = ref<Turn[]>([])
const answering = ref<Turn | null>(null)

const assistant = useAiStream('assistant.ask', {
  onDelta: (delta) => {
    if (answering.value) answering.value.text += delta
  },
  onDone: (full) => {
    if (answering.value) answering.value.text = full
    answering.value = null
  },
})

/** The answer being written has no text yet, so the turn shows a spinner. */
const pending = computed(() => assistant.isStreaming.value && !answering.value?.text)

const ask = async (): Promise<void> => {
  const asked = question.value.trim()
  if (!asked || assistant.isStreaming.value) return

  question.value = ''
  turns.value.push({ id: `q-${turns.value.length}`, role: 'user', text: asked })

  const answer: Turn = { id: `a-${turns.value.length}`, role: 'assistant', text: '' }
  turns.value.push(answer)
  answering.value = answer

  await assistant.run({ question: asked })
}
</script>

<template>
  <div class="flex h-full min-h-0 flex-col gap-4">
    <h1 class="text-2xl font-semibold">{{ t('ai.title') }}</h1>

    <MessageScrollerProvider>
      <MessageScroller class="min-h-0 flex-1 rounded-lg border border-border bg-card">
        <MessageScrollerViewport class="px-4 py-6">
          <MessageScrollerContent>
            <Marker
              v-if="!turns.length"
              variant="separator"
            >
              <MarkerContent>{{ t('ai.empty') }}</MarkerContent>
            </Marker>

            <MessageScrollerItem
              v-for="turn in turns"
              :key="turn.id"
              :message-id="turn.id"
            >
              <Message :align="turn.role === 'user' ? 'end' : 'start'">
                <MessageContent>
                  <Bubble
                    :align="turn.role === 'user' ? 'end' : 'start'"
                    :variant="turn.role === 'user' ? 'default' : 'muted'"
                  >
                    <BubbleContent class="whitespace-pre-wrap">
                      <Spinner v-if="turn === answering && pending" />
                      <template v-else>{{ turn.text }}</template>
                    </BubbleContent>
                  </Bubble>
                  <MessageFooter v-if="turn === answering && assistant.status.value">
                    {{ assistant.status.value }}
                  </MessageFooter>
                </MessageContent>
              </Message>
            </MessageScrollerItem>

            <Marker
              v-if="assistant.error.value"
              variant="border"
              class="text-destructive"
            >
              <MarkerIcon><Icon name="lucide:triangle-alert" /></MarkerIcon>
              <MarkerContent>{{ assistant.error.value }}</MarkerContent>
            </Marker>
          </MessageScrollerContent>
        </MessageScrollerViewport>

        <MessageScrollerButton class="end-4 start-auto translate-x-0" />
      </MessageScroller>
    </MessageScrollerProvider>

    <form @submit.prevent="ask">
      <InputGroup>
        <InputGroupTextarea
          v-model="question"
          :rows="2"
          :placeholder="t('ai.placeholder')"
          :aria-label="t('ai.label')"
          @keydown.enter.exact.prevent="ask"
        />
        <InputGroupAddon align="block-end">
          <InputGroupButton
            v-if="assistant.isStreaming.value"
            size="sm"
            class="ml-auto"
            @click="assistant.cancel()"
          >
            {{ t('ai.stop') }}
          </InputGroupButton>
          <InputGroupButton
            v-else
            type="submit"
            variant="primary"
            size="sm"
            class="ml-auto"
            :disabled="!question.trim()"
          >
            {{ t('ai.ask') }}
            <Icon name="lucide:arrow-up" />
          </InputGroupButton>
        </InputGroupAddon>
      </InputGroup>
    </form>
  </div>
</template>
