<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import MonacoEditor from '../editors/MonacoEditor.vue'
import type { SchemaField } from '../../types/schema'

const props = defineProps<{
  schema: Record<string, any> | null
  modelValue?: Record<string, any>
}>()

const emit = defineEmits<{
  'update:modelValue': [value: Record<string, any>]
}>()

const formData = ref<Record<string, any>>(props.modelValue || {})

watch(() => props.modelValue, (newValue) => {
  if (newValue) {
    formData.value = newValue
  }
}, { deep: true })

watch(formData, (newValue) => {
  emit('update:modelValue', newValue)
}, { deep: true })

const fields = computed(() => {
  if (!props.schema || !props.schema.properties) return []
  
  return Object.entries(props.schema.properties).map(([key, field]: [string, any]) => ({
    key,
    ...field,
    required: props.schema.required?.includes(key) || false,
  }))
})

function getFieldComponent(field: any): string {
  // Determine which input component to use
  if (field.type === 'array') return 'json-editor'
  if (field.type === 'object') return 'json-editor'
  if (field.enum) return 'select'
  if (field.type === 'boolean') return 'checkbox'
  if (field.type === 'number' || field.type === 'integer') return 'number'
  if (field.format === 'date') return 'date'
  if (field.format === 'date-time') return 'datetime'
  if (field.format === 'email') return 'email'
  if (field.maxLength && field.maxLength > 200) return 'textarea'
  return 'text'
}

// JSON editor helpers for complex types
function getJsonString(value: any): string {
  return JSON.stringify(value, null, 2)
}

function updateJsonField(key: string, jsonString: string) {
  try {
    formData.value[key] = JSON.parse(jsonString)
  } catch (e) {
    // Keep invalid JSON - don't update
  }
}

function initializeFieldValue(field: any) {
  if (formData.value[field.key] !== undefined) return
  
  if (field.default !== undefined) {
    formData.value[field.key] = field.default
  } else if (field.type === 'boolean') {
    formData.value[field.key] = false
  } else if (field.type === 'number' || field.type === 'integer') {
    formData.value[field.key] = 0
  } else if (field.type === 'array') {
    formData.value[field.key] = []
  } else if (field.type === 'object') {
    formData.value[field.key] = {}
  } else {
    formData.value[field.key] = ''
  }
}

// Initialize form data with defaults
watch(() => props.schema, (newSchema) => {
  if (newSchema && newSchema.properties) {
    fields.value.forEach(field => {
      initializeFieldValue(field)
    })
  }
}, { immediate: true })
</script>

<template>
  <div class="form-preview">
    <div v-if="!schema || !schema.properties || fields.length === 0" class="empty-state">
      <va-icon name="list_alt" size="large" color="secondary" />
      <p>No schema fields defined yet</p>
    </div>
    
    <div v-else class="form-fields">
      <div
        v-for="field in fields"
        :key="field.key"
        class="form-field"
      >
        <!-- Text Input -->
        <va-input
          v-if="getFieldComponent(field) === 'text'"
          v-model="formData[field.key]"
          :label="field.title || field.key"
          :placeholder="field.description"
          :required="field.required"
          :disabled="field.readOnly"
        />
        
        <!-- Email Input -->
        <va-input
          v-else-if="getFieldComponent(field) === 'email'"
          v-model="formData[field.key]"
          :label="field.title || field.key"
          :placeholder="field.description"
          type="email"
          :required="field.required"
        />
        
        <!-- Number Input -->
        <va-input
          v-else-if="getFieldComponent(field) === 'number'"
          v-model.number="formData[field.key]"
          :label="field.title || field.key"
          :placeholder="field.description"
          type="number"
          :min="field.minimum"
          :max="field.maximum"
          :required="field.required"
        />
        
        <!-- Date Input -->
        <va-date-input
          v-else-if="getFieldComponent(field) === 'date'"
          v-model="formData[field.key]"
          :label="field.title || field.key"
          :required="field.required"
        />
        
        <!-- Textarea -->
        <va-textarea
          v-else-if="getFieldComponent(field) === 'textarea'"
          v-model="formData[field.key]"
          :label="field.title || field.key"
          :placeholder="field.description"
          :required="field.required"
          :min-rows="3"
          autosize
        />
        
        <!-- Select -->
        <va-select
          v-else-if="getFieldComponent(field) === 'select'"
          v-model="formData[field.key]"
          :label="field.title || field.key"
          :options="field.enum"
          :required="field.required"
        />
        
        <!-- Checkbox -->
        <va-checkbox
          v-else-if="getFieldComponent(field) === 'checkbox'"
          v-model="formData[field.key]"
          :label="field.title || field.key"
        />
        
        <!-- Array/Object JSON Editor -->
        <div v-else-if="getFieldComponent(field) === 'json-editor'" class="json-field">
          <label class="json-label">
            {{ field.title || field.key }}
            <va-badge v-if="field.required" color="warning" text="Required" size="small" />
          </label>
          <div class="json-editor-container">
            <MonacoEditor
              :model-value="getJsonString(formData[field.key])"
              @update:model-value="updateJsonField(field.key, $event)"
              language="json"
              :height="200"
              :font-size="12"
            />
          </div>
        </div>
        
        <!-- Help text -->
        <div v-if="field.description" class="field-help">
          {{ field.description }}
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.form-preview {
  padding: 1rem;
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem;
  color: var(--va-text-secondary);
  text-align: center;
}

.empty-state p {
  margin-top: 1rem;
}

.form-fields {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.form-field {
  display: flex;
  flex-direction: column;
}

.field-help {
  font-size: 0.75rem;
  color: var(--va-text-secondary);
  margin-top: 0.25rem;
  margin-left: 0.25rem;
}

.json-field {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.json-label {
  font-size: 0.875rem;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.json-editor-container {
  border: 1px solid var(--va-background-border);
  border-radius: 0.25rem;
  overflow: hidden;
}
</style>
