<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { useFamiliesStore } from '../stores/families'
import type { TemplateFamily } from '../stores/families'

const props = defineProps<{
  visible: boolean
  family: TemplateFamily | null
}>()

const emit = defineEmits<{
  'update:visible': [value: boolean]
  'close': []
  'saved': [family: TemplateFamily]
}>()

const familiesStore = useFamiliesStore()

// Form fields
const formData = ref({
  name: '',
  slug: '',
  description: '',
  tags: [] as string[],
  is_active: true,
})

const tagInput = ref('')
const isSaving = ref(false)
const errors = ref<Record<string, string>>({})

const isEditing = computed(() => props.family !== null)
const drawerTitle = computed(() => isEditing.value ? 'Edit Family' : 'Create Family')

// Watch for family prop changes to populate form
watch(() => props.family, (family) => {
  if (family) {
    formData.value = {
      name: family.name,
      slug: family.slug,
      description: family.description || '',
      tags: family.tags || [],
      is_active: family.is_active,
    }
  } else {
    resetForm()
  }
}, { immediate: true })

// Auto-generate slug from name
watch(() => formData.value.name, (name) => {
  if (!isEditing.value && name) {
    formData.value.slug = name
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/(^-|-$)/g, '')
  }
})

function resetForm() {
  formData.value = {
    name: '',
    slug: '',
    description: '',
    tags: [],
    is_active: true,
  }
  tagInput.value = ''
  errors.value = {}
}

function addTag() {
  const tag = tagInput.value.trim()
  if (tag && !formData.value.tags.includes(tag)) {
    formData.value.tags.push(tag)
    tagInput.value = ''
  }
}

function removeTag(index: number) {
  formData.value.tags.splice(index, 1)
}

function validateForm(): boolean {
  errors.value = {}

  if (!formData.value.name) {
    errors.value.name = 'Name is required'
  }

  if (!formData.value.slug) {
    errors.value.slug = 'Slug is required'
  } else if (!/^[a-z0-9-]+$/.test(formData.value.slug)) {
    errors.value.slug = 'Slug can only contain lowercase letters, numbers, and hyphens'
  }

  return Object.keys(errors.value).length === 0
}

async function handleSave() {
  if (!validateForm()) return

  isSaving.value = true
  
  try {
    let savedFamily: TemplateFamily

    if (isEditing.value && props.family) {
      savedFamily = await familiesStore.updateFamily(props.family.id, formData.value)
    } else {
      savedFamily = await familiesStore.createFamily(formData.value)
    }

    emit('saved', savedFamily)
    handleClose()
  } catch (error: any) {
    // Handle validation errors from API
    if (error.response?.data?.errors) {
      errors.value = error.response.data.errors
    }
  } finally {
    isSaving.value = false
  }
}

function handleClose() {
  emit('update:visible', false)
  emit('close')
  setTimeout(resetForm, 300) // Reset after drawer animation
}
</script>

<template>
  <va-sidebar
    :model-value="visible"
    @update:model-value="(value) => emit('update:visible', value)"
    position="right"
    width="600px"
    color="background-secondary"
  >
    <template #header>
      <div class="drawer-header">
        <h2 class="drawer-title">{{ drawerTitle }}</h2>
        <va-button
          preset="plain"
          icon="close"
          @click="handleClose"
        />
      </div>
    </template>

    <div class="drawer-content">
      <div class="family-form">
        <!-- Name Field -->
        <va-input
          v-model="formData.name"
          label="Name"
          placeholder="e.g., Election Returns"
          :error="!!errors.name"
          :error-messages="errors.name ? [errors.name] : []"
          required-mark
        />

        <!-- Slug Field -->
        <va-input
          v-model="formData.slug"
          label="Slug"
          placeholder="e.g., election-returns"
          :error="!!errors.slug"
          :error-messages="errors.slug ? [errors.slug] : []"
          required-mark
        >
          <template #appendInner>
            <va-icon
              v-if="!errors.slug && formData.slug"
              name="check_circle"
              color="success"
            />
          </template>
        </va-input>

        <!-- Description Field -->
        <va-textarea
          v-model="formData.description"
          label="Description"
          placeholder="Describe this template family..."
          :min-rows="3"
          :max-rows="6"
        />

        <!-- Tags Field -->
        <div class="form-group">
          <label class="va-input-label">Tags</label>
          <div class="tags-container">
            <va-chip
              v-for="(tag, index) in formData.tags"
              :key="index"
              closeable
              @update:model-value="removeTag(index)"
              size="small"
              color="primary"
            >
              {{ tag }}
            </va-chip>
          </div>
          <div class="tag-input-row">
            <va-input
              v-model="tagInput"
              placeholder="Add a tag..."
              @keyup.enter="addTag"
            />
            <va-button
              @click="addTag"
              :disabled="!tagInput.trim()"
              size="small"
            >
              Add
            </va-button>
          </div>
        </div>

        <!-- Active Status -->
        <va-switch
          v-model="formData.is_active"
          label="Active"
          class="mt-4"
        >
          <template #innerLabel>
            <div class="switch-labels">
              <span>Inactive</span>
              <span>Active</span>
            </div>
          </template>
        </va-switch>

        <div class="form-hint">
          {{ formData.is_active ? 'This family is active and visible' : 'This family is inactive and hidden' }}
        </div>
      </div>
    </div>

    <template #footer>
      <div class="drawer-footer">
        <va-button
          preset="secondary"
          @click="handleClose"
          :disabled="isSaving"
        >
          Cancel
        </va-button>
        <va-button
          @click="handleSave"
          :loading="isSaving"
          :disabled="isSaving"
        >
          {{ isEditing ? 'Save Changes' : 'Create Family' }}
        </va-button>
      </div>
    </template>
  </va-sidebar>
</template>

<style scoped>
.drawer-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  border-bottom: 1px solid var(--va-background-border);
}

.drawer-title {
  font-size: 1.25rem;
  font-weight: 600;
  margin: 0;
}

.drawer-content {
  padding: 1.5rem;
  overflow-y: auto;
  max-height: calc(100vh - 180px);
}

.family-form {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.va-input-label {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--va-text-primary);
  margin-bottom: 0.25rem;
}

.tags-container {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  min-height: 32px;
  padding: 0.5rem;
  background: var(--va-background-element);
  border-radius: 0.5rem;
}

.tag-input-row {
  display: flex;
  gap: 0.5rem;
  align-items: flex-end;
}

.tag-input-row .va-input {
  flex: 1;
}

.switch-labels {
  display: flex;
  gap: 2rem;
  font-size: 0.875rem;
}

.form-hint {
  font-size: 0.75rem;
  color: var(--va-text-secondary);
  margin-top: -0.5rem;
}

.drawer-footer {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  padding: 1.5rem;
  border-top: 1px solid var(--va-background-border);
}
</style>
