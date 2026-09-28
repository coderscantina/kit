<script setup lang="ts">
import SettingsSection from '~/components/account/SettingsSection.vue'
import { InputField } from '~/components/ui/form'
import { useI18n } from '~/plugins/i18n'

/**
 * How to point a model at this app. The endpoint speaks MCP over HTTP; the
 * snippet is the `mcpServers` shape most clients read, with the token left
 * for the user to paste, since it is never shown again after creation.
 */
const { t } = useI18n()

const endpoint = `${window.location.origin}/mcp`

const snippet = JSON.stringify(
  {
    mcpServers: {
      [window.location.hostname]: {
        type: 'http',
        url: endpoint,
        headers: { Authorization: 'Bearer <token>' },
      },
    },
  },
  null,
  2
)
</script>

<template>
  <SettingsSection
    id="mcp"
    :title="t('mcp.title')"
    :description="t('mcp.description')"
  >
    <div class="grid gap-4">
      <InputField
        name="mcp-endpoint"
        :label="t('mcp.endpoint')"
        :model-value="endpoint"
        :actions="['copy']"
        input-class="font-mono"
        readonly
      />
      <div class="grid gap-1.5">
        <span class="text-sm font-medium text-primary">{{ t('mcp.config') }}</span>
        <pre
          class="overflow-x-auto rounded-lg bg-muted-background p-3 font-mono text-xs text-primary"
        ><code>{{ snippet }}</code></pre>
        <p class="text-xs text-muted">{{ t('mcp.configHint') }}</p>
      </div>
    </div>
  </SettingsSection>
</template>
