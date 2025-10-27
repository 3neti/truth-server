<script setup lang="ts">
import { ref, computed } from 'vue'
import type { SchemaField } from '../../types/schema'
import draggable from 'vuedraggable'

const props = defineProps<{
  schema: Record<string, any> | null
}>()

const emit = defineEmits<{
  'update:schema': [value: Record<string, any>]
}>()

const fieldTypes = [
  { text: 'Text', value: 'string' },
  { text: 'Number', value: 'number' },
  { text: 'Integer', value: 'integer' },
  { text: 'Boolean', value: 'boolean' },
  { text: 'Date', value: 'string', format: 'date' },
  { text: 'Date & Time', value: 'string', format: 'date-time' },
  { text: 'Email', value: 'string', format: 'email' },
  { text: 'Array', value: 'array' },
  { text: 'Object', value: 'object' },
]

const formatOptions = [
  { text: 'None', value: null },
  { text: 'Date', value: 'date' },
  { text: 'Date & Time', value: 'date-time' },
  { text: 'Email', value: 'email' },
  { text: 'URI', value: 'uri' },
]

interface EditableField extends SchemaField {
  key: string
  required: boolean
}

const fields = computed<EditableField[]>(() => {
  if (!props.schema || !props.schema.properties) return []
  
  return Object.entries(props.schema.properties).map(([key, field]: [string, any]) => ({
    key,
    ...field,
    required: props.schema.required?.includes(key) || false,
  }))
})

const editingField = ref<EditableField | null>(null)
const showFieldEditor = ref(false)
const editingIndex = ref<number | null>(null)

function addField() {
  const newField: EditableField = {
    key: `field_${Date.now()}`,
    type: 'string',
    title: 'New Field',
    description: '',
    required: false,
  }
  
  editingField.value = newField
  editingIndex.value = null
  showFieldEditor.value = true
}

function editField(field: EditableField, index: number) {
  editingField.value = { ...field }
  editingIndex.value = index
  showFieldEditor.value = true
}

function saveField() {
  if (!editingField.value) return
  
  const updatedSchema = props.schema ? { ...props.schema } : { type: 'object', properties: {}, required: [] }
  
  if (!updatedSchema.properties) {
    updatedSchema.properties = {}
  }
  
  if (!updatedSchema.required) {
    updatedSchema.required = []
  }
  
  // Remove old key if editing and key changed
  if (editingIndex.value !== null) {
    const oldKey = fields.value[editingIndex.value].key
    if (oldKey !== editingField.value.key) {
      delete updatedSchema.properties[oldKey]
      updatedSchema.required = updatedSchema.required.filter((k: string) => k !== oldKey)
    }
  }
  
  // Add or update field
  const { key, required, ...fieldData } = editingField.value
  updatedSchema.properties[key] = fieldData
  
  // Update required array
  if (required && !updatedSchema.required.includes(key)) {
    updatedSchema.required.push(key)
  } else if (!required) {
    updatedSchema.required = updatedSchema.required.filter((k: string) => k !== key)
  }
  
  emit('update:schema', updatedSchema)
  closeFieldEditor()
}

function deleteField(field: EditableField) {
  if (!props.schema) return
  
  const updatedSchema = { ...props.schema }
  delete updatedSchema.properties[field.key]
  updatedSchema.required = updatedSchema.required?.filter((k: string) => k !== field.key)
  
  emit('update:schema', updatedSchema)
}

function closeFieldEditor() {
  showFieldEditor.value = false
  editingField.value = null
  editingIndex.value = null
}

function onFieldsReordered(event: any) {
  if (!props.schema || !props.schema.properties) return
  
  const updatedProperties: Record<string, any> = {}
  fields.value.forEach(field => {
    const { key, required, ...fieldData } = field
    updatedProperties[key] = fieldData
  })
  
  emit('update:schema', {
    ...props.schema,
    properties: updatedProperties,
  })
}

// Enum options management
const enumInput = ref('')

function addEnumOption() {
  if (!editingField.value || !enumInput.value.trim()) return
  
  if (!editingField.value.enum) {
    editingField.value.enum = []
  }
  
  editingField.value.enum.push(enumInput.value.trim())
  enumInput.value = ''
}

function removeEnumOption(index: number) {
  if (!editingField.value?.enum) return
  editingField.value.enum.splice(index, 1)
}
</script>

<template>
  <div class="schema-form-builder">
    <div class="builder-header">
      <h3>Schema Fields</h3>
      <va-button @click="addField" icon="add" size="small">
        Add Field
      </va-button>
    </div>
    
    <div v-if="fields.length === 0" class="empty-state">
      <va-icon name="schema" size="large" color="secondary" />
      <p>No fields defined yet</p>
      <va-button @click="addField" size="small">Add Your First Field</va-button>
    </div>
    
    <draggable
      v-else
      v-model="fields"
      @end="onFieldsReordered"
      item-key="key"
      class="fields-list"
      handle=".drag-handle"
    >
      <template #item="{ element: field, index }">
        <va-card class="field-item" :key="field.key">
          <va-card-content class="field-content">
            <div class="drag-handle">
              <va-icon name="drag_indicator" />
            </div>
            
            <div class="field-info">
              <div class="field-name">
                <strong>{{ field.title || field.key }}</strong>
                <va-badge v-if="field.required" color="warning" text="Required" />
              </div>
              <div class="field-details">
                <span class="field-type">{{ field.type }}</span>
                <span v-if="field.format" class="field-format">• {{ field.format }}</span>
                <span v-if="field.description" class="field-description">• {{ field.description }}</span>
              </div>
            </div>
            
            <div class="field-actions">
              <va-button
                preset="plain"
                icon="edit"
                size="small"
                @click="editField(field, index)"
              />
              <va-button
                preset="plain"
                icon="delete"
                size="small"
                color="danger"
                @click="deleteField(field)"
              />
            </div>
          </va-card-content>
        </va-card>
      </template>
    </draggable>
    
    <!-- Field Editor Modal -->
    <va-modal
      v-model="showFieldEditor"
      title="Edit Field"
      size="large"
      @ok="saveField"
      @cancel="closeFieldEditor"
    >
      <div v-if="editingField" class="field-editor">
        <va-input
          v-model="editingField.key"
          label="Field Key"
          placeholder="field_name"
          required
        />
        
        <va-input
          v-model="editingField.title"
          label="Field Title"
          placeholder="Display name"
        />
        
        <va-textarea
          v-model="editingField.description"
          label="Description"
          placeholder="Help text for this field"
          :min-rows="2"
          autosize
        />
        
        <va-select
          v-model="editingField.type"
          label="Field Type"
          :options="fieldTypes"
          text-by="text"
          value-by="value"
        />
        
        <va-select
          v-if="editingField.type === 'string'"
          v-model="editingField.format"
          label="Format"
          :options="formatOptions"
          text-by="text"
          value-by="value"
          clearable
        />
        
        <va-checkbox
          v-model="editingField.required"
          label="Required Field"
        />
        
        <va-input
          v-if="editingField.type === 'string'"
          v-model.number="editingField.minLength"
          label="Minimum Length"
          type="number"
          :min="0"
        />
        
        <va-input
          v-if="editingField.type === 'string'"
          v-model.number="editingField.maxLength"
          label="Maximum Length"
          type="number"
          :min="0"
        />
        
        <va-input
          v-if="editingField.type === 'number' || editingField.type === 'integer'"
          v-model.number="editingField.minimum"
          label="Minimum Value"
          type="number"
        />
        
        <va-input
          v-if="editingField.type === 'number' || editingField.type === 'integer'"
          v-model.number="editingField.maximum"
          label="Maximum Value"
          type="number"
        />
        
        <va-input
          v-model="editingField.default"
          label="Default Value"
          placeholder="Optional default value"
        />
        
        <!-- Enum Options -->
        <div v-if="editingField.type === 'string'" class="enum-editor">
          <div class="enum-header">
            <label>Enum Options (for select fields)</label>
          </div>
          
          <div class="enum-input">
            <va-input
              v-model="enumInput"
              placeholder="Add option"
              @keyup.enter="addEnumOption"
            />
            <va-button
              @click="addEnumOption"
              icon="add"
              size="small"
            >
              Add
            </va-button>
          </div>
          
          <div v-if="editingField.enum && editingField.enum.length > 0" class="enum-list">
            <va-chip
              v-for="(option, idx) in editingField.enum"
              :key="idx"
              closable
              @update:model-value="removeEnumOption(idx)"
            >
              {{ option }}
            </va-chip>
          </div>
        </div>
      </div>
    </va-modal>
  </div>
</template>

<style scoped>
.schema-form-builder {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.builder-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  border-bottom: 1px solid var(--va-background-border);
}

.builder-header h3 {
  margin: 0;
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem;
  color: var(--va-text-secondary);
  text-align: center;
  gap: 1rem;
}

.fields-list {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  padding: 1rem;
}

.field-item {
  cursor: move;
}

.field-content {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 0.75rem !important;
}

.drag-handle {
  cursor: grab;
  color: var(--va-text-secondary);
}

.drag-handle:active {
  cursor: grabbing;
}

.field-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.field-name {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.field-details {
  font-size: 0.875rem;
  color: var(--va-text-secondary);
  display: flex;
  gap: 0.5rem;
}

.field-type {
  font-family: monospace;
  background: var(--va-background-element);
  padding: 0.125rem 0.375rem;
  border-radius: 0.25rem;
}

.field-format {
  font-style: italic;
}

.field-actions {
  display: flex;
  gap: 0.25rem;
}

.field-editor {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  padding: 1rem 0;
}

.enum-editor {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.enum-header label {
  font-size: 0.875rem;
  font-weight: 500;
}

.enum-input {
  display: flex;
  gap: 0.5rem;
  align-items: flex-end;
}

.enum-list {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}
</style>
