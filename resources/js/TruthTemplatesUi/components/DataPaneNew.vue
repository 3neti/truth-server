<script setup lang="ts">
import { DataEditor } from '@lbhurtado/vue-data-editor'

defineProps<{
  modelValue: Record<string, any>
}>()

const emit = defineEmits<{
  'update:modelValue': [value: Record<string, any>]
}>()

function updateValue(value: Record<string, any>) {
  emit('update:modelValue', value)
}
</script>

<template>
  <div class="flex flex-col h-full">
    <DataEditor :model-value="modelValue" @update:model-value="updateValue" />
  </div>
</template>
