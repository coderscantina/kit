<script setup lang="ts">
import { Input } from '~/components/ui/input'
import { Label } from '~/components/ui/label'

defineProps<{
  id: string
  label: string
  type?: string
  autocomplete?: string
  error?: string
  required?: boolean
}>()

const model = defineModel<string>({ default: '' })
</script>

<template>
  <div class="space-y-2">
    <Label :for="id">{{ label }}</Label>
    <Input
      :id="id"
      v-model="model"
      :type="type ?? 'text'"
      :autocomplete="autocomplete"
      :required="required"
      :aria-invalid="error ? true : undefined"
    />
    <p
      v-if="error"
      class="text-sm text-destructive"
    >
      {{ error }}
    </p>
  </div>
</template>
