<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from '../../layouts/AdminLayout.vue'
import MonacoEditor from '../../components/editors/MonacoEditor.vue'
import SchemaFormBuilder from '../../components/schema/SchemaFormBuilder.vue'
import FormPreview from '../../components/schema/FormPreview.vue'
import { useTemplatesStore } from '../../stores/templates'
import { useFamiliesStore } from '../../stores/families'
import { debounce } from 'lodash-es'

const props = defineProps<{
  templateId?: string | number
}>()

const templatesStore = useTemplatesStore()
const familiesStore = useFamiliesStore()

// Editor state
const activeTab = ref<'handlebars' | 'schema' | 'schemaBuilder' | 'sample' | 'metadata'>('handlebars')
const previewTab = ref<'spec' | 'pdf'>('spec')
const schemaViewMode = ref<'json' | 'visual'>('json')

// Form data
const formData = ref({
  name: '',
  description: '',
  category: '',
  family_id: null as number | null,
  layout_variant: '',
  handlebars_template: '',
  sample_data: {} as Record<string, any>,
  json_schema: {} as Record<string, any>,
  is_public: true,
})

// Preview state
const compiledSpec = ref<Record<string, any> | null>(null)
const pdfUrl = ref<string | null>(null)
const compiling = ref(false)
const rendering = ref(false)
const compilationError = ref<string | null>(null)
const validationErrors = ref<Record<string, string[]>>({})

// UI state
const saving = ref(false)
const hasUnsavedChanges = ref(false)

const isEditing = computed(() => !!props.templateId)
const pageTitle = computed(() => isEditing.value ? 'Edit Template' : 'Create Template')

// Formatted JSON for editors
const sampleDataJson = computed({
  get: () => JSON.stringify(formData.value.sample_data, null, 2),
  set: (value: string) => {
    try {
      formData.value.sample_data = JSON.parse(value)
    } catch (e) {
      // Keep invalid JSON as is
    }
  }
})

const schemaJson = computed({
  get: () => JSON.stringify(formData.value.json_schema, null, 2),
  set: (value: string) => {
    try {
      formData.value.json_schema = JSON.parse(value)
    } catch (e) {
      // Keep invalid JSON as is
    }
  }
})

const compiledSpecJson = computed(() => 
  compiledSpec.value ? JSON.stringify(compiledSpec.value, null, 2) : ''
)

onMounted(async () => {
  await familiesStore.fetchFamilies()
  
  if (isEditing.value && props.templateId) {
    await loadTemplate()
  }
})

async function loadTemplate() {
  try {
    const template = await templatesStore.fetchTemplate(props.templateId!)
    formData.value = {
      name: template.name,
      description: template.description || '',
      category: template.category,
      family_id: template.family_id,
      layout_variant: template.layout_variant || '',
      handlebars_template: template.handlebars_template,
      sample_data: template.sample_data || {},
      json_schema: template.json_schema || {},
      is_public: template.is_public,
    }
  } catch (error) {
    console.error('Failed to load template:', error)
  }
}

// Debounced auto-compile
const debouncedCompile = debounce(async () => {
  await compileTemplate()
}, 500)

watch([
  () => formData.value.handlebars_template,
  () => formData.value.sample_data,
], () => {
  hasUnsavedChanges.value = true
  debouncedCompile()
})

async function compileTemplate() {
  if (!formData.value.handlebars_template || !formData.value.sample_data) {
    return
  }

  compiling.value = true
  compilationError.value = null

  try {
    const result = await templatesStore.compileTemplate(
      formData.value.handlebars_template,
      formData.value.sample_data
    )
    
    if (result.success) {
      compiledSpec.value = result.spec
      compilationError.value = null
    }
  } catch (error: any) {
    compilationError.value = error.response?.data?.error || error.message || 'Compilation failed'
    compiledSpec.value = null
  } finally {
    compiling.value = false
  }
}

async function renderPDF() {
  if (!compiledSpec.value) {
    await compileTemplate()
    if (!compiledSpec.value) return
  }

  rendering.value = true

  try {
    const result = await templatesStore.renderTemplate(compiledSpec.value)
    
    if (result.success) {
      pdfUrl.value = result.pdf_url
      previewTab.value = 'pdf'
    }
  } catch (error: any) {
    console.error('Rendering failed:', error)
  } finally {
    rendering.value = false
  }
}

function validateForm(): boolean {
  validationErrors.value = {}

  if (!formData.value.name) {
    validationErrors.value.name = ['Name is required']
  }

  if (!formData.value.category) {
    validationErrors.value.category = ['Category is required']
  }

  if (!formData.value.handlebars_template) {
    validationErrors.value.handlebars_template = ['Template content is required']
  }

  return Object.keys(validationErrors.value).length === 0
}

async function saveTemplate() {
  if (!validateForm()) return

  saving.value = true

  try {
    if (isEditing.value && props.templateId) {
      await templatesStore.updateTemplate(props.templateId, formData.value)
    } else {
      const newTemplate = await templatesStore.createTemplate(formData.value)
      router.visit(`/admin/templates/${newTemplate.id}/edit`)
    }
    
    hasUnsavedChanges.value = false
  } catch (error: any) {
    if (error.response?.data?.errors) {
      validationErrors.value = error.response.data.errors
    }
  } finally {
    saving.value = false
  }
}

function goBack() {
  if (hasUnsavedChanges.value) {
    const confirmed = confirm('You have unsaved changes. Are you sure you want to leave?')
    if (!confirmed) return
  }
  router.visit('/admin/templates')
}
</script>

<template>
  <AdminLayout>
    <div class="template-editor">
      <!-- Header -->
      <div class="editor-header">
        <div class="header-left">
          <va-button
            preset="plain"
            icon="arrow_back"
            @click="goBack"
          />
          <div>
            <h1 class="page-title">{{ pageTitle }}</h1>
            <p v-if="formData.name" class="page-subtitle">{{ formData.name }}</p>
          </div>
        </div>
        
        <div class="header-actions">
          <va-chip v-if="hasUnsavedChanges" color="warning" size="small">
            Unsaved changes
          </va-chip>
          <va-button
            @click="compileTemplate"
            :loading="compiling"
            preset="secondary"
            icon="build"
          >
            Compile
          </va-button>
          <va-button
            @click="renderPDF"
            :loading="rendering"
            :disabled="!compiledSpec"
            preset="secondary"
            icon="picture_as_pdf"
          >
            Render PDF
          </va-button>
          <va-button
            @click="saveTemplate"
            :loading="saving"
            color="primary"
            icon="save"
          >
            Save
          </va-button>
        </div>
      </div>

      <!-- Split Pane Layout -->
      <div class="editor-content">
        <!-- Left Pane - Editor -->
        <div class="left-pane">
          <div class="left-pane-header">
            <va-tabs v-model="activeTab">
              <template #tabs>
                <va-tab name="handlebars">
                  <va-icon name="code" class="tab-icon" />
                  Template
                </va-tab>
                <va-tab name="schema">
                  <va-icon name="schema" class="tab-icon" />
                  Schema (JSON)
                </va-tab>
                <va-tab name="schemaBuilder">
                  <va-icon name="view_list" class="tab-icon" />
                  Schema (Visual)
                </va-tab>
                <va-tab name="sample">
                  <va-icon name="data_object" class="tab-icon" />
                  Sample Data
                </va-tab>
                <va-tab name="metadata">
                  <va-icon name="info" class="tab-icon" />
                  Metadata
                </va-tab>
              </template>
            </va-tabs>
          </div>

          <div class="tab-content">
            <!-- Handlebars Template Tab -->
            <div v-show="activeTab === 'handlebars'" class="editor-tab">
              <MonacoEditor
                v-model="formData.handlebars_template"
                language="handlebars"
                :font-size="14"
              />
            </div>

            <!-- JSON Schema Tab -->
            <div v-show="activeTab === 'schema'" class="editor-tab">
              <MonacoEditor
                v-model="schemaJson"
                language="json"
                :font-size="14"
              />
            </div>

            <!-- Visual Schema Builder Tab -->
            <div v-show="activeTab === 'schemaBuilder'" class="editor-tab schema-builder-tab">
              <SchemaFormBuilder
                :schema="formData.json_schema"
                @update:schema="formData.json_schema = $event"
              />
            </div>

            <!-- Sample Data Tab -->
            <div v-show="activeTab === 'sample'" class="editor-tab">
              <MonacoEditor
                v-model="sampleDataJson"
                language="json"
                :font-size="14"
              />
            </div>

            <!-- Metadata Tab -->
            <div v-show="activeTab === 'metadata'" class="metadata-tab">
              <div class="metadata-form">
                <va-input
                  v-model="formData.name"
                  label="Template Name"
                  placeholder="e.g., Election Ballot"
                  :error="!!validationErrors.name"
                  :error-messages="validationErrors.name"
                  required-mark
                />

                <va-textarea
                  v-model="formData.description"
                  label="Description"
                  placeholder="Describe this template..."
                  :min-rows="3"
                />

                <va-select
                  v-model="formData.family_id"
                  :options="familiesStore.families"
                  label="Template Family"
                  placeholder="Select family..."
                  text-by="name"
                  value-by="id"
                  clearable
                />

                <va-input
                  v-model="formData.category"
                  label="Category"
                  placeholder="e.g., election, survey, test"
                  :error="!!validationErrors.category"
                  :error-messages="validationErrors.category"
                  required-mark
                />

                <va-input
                  v-model="formData.layout_variant"
                  label="Layout Variant"
                  placeholder="e.g., standard, compact, detailed"
                />

                <va-switch
                  v-model="formData.is_public"
                  label="Public Template"
                >
                  <template #innerLabel>
                    <div class="switch-labels">
                      <span>Private</span>
                      <span>Public</span>
                    </div>
                  </template>
                </va-switch>
              </div>
            </div>
          </div>
        </div>

        <!-- Right Pane - Preview -->
        <div class="right-pane">
          <div class="preview-header">
            <h3 class="preview-title">Preview</h3>
            <va-button-toggle
              v-model="previewTab"
              :options="[
                { label: 'JSON Spec', value: 'spec' },
                { label: 'PDF', value: 'pdf' },
              ]"
              size="small"
            />
          </div>

          <div class="preview-content">
            <!-- Compilation Error -->
            <div v-if="compilationError" class="error-state">
              <va-icon name="error" size="large" color="danger" />
              <p><strong>Compilation Error:</strong></p>
              <pre>{{ compilationError }}</pre>
            </div>

            <!-- Compiling State -->
            <div v-else-if="compiling" class="loading-state">
              <va-progress-circle indeterminate />
              <p>Compiling template...</p>
            </div>

            <!-- JSON Spec Preview -->
            <div v-else-if="previewTab === 'spec'" class="spec-preview">
              <div v-if="!compiledSpec" class="empty-state">
                <va-icon name="visibility_off" size="large" color="secondary" />
                <p>No compiled spec yet</p>
                <p class="text-sm">Make changes to see the preview</p>
              </div>
              <MonacoEditor
                v-else
                :model-value="compiledSpecJson"
                language="json"
                :read-only="true"
                :font-size="13"
              />
            </div>

            <!-- PDF Preview -->
            <div v-else-if="previewTab === 'pdf'" class="pdf-preview">
              <div v-if="rendering" class="loading-state">
                <va-progress-circle indeterminate />
                <p>Rendering PDF...</p>
              </div>
              <div v-else-if="!pdfUrl" class="empty-state">
                <va-icon name="picture_as_pdf" size="large" color="secondary" />
                <p>No PDF generated yet</p>
                <va-button @click="renderPDF" :disabled="!compiledSpec">
                  Render PDF
                </va-button>
              </div>
              <iframe
                v-else
                :src="`${pdfUrl}#view=FitH&toolbar=1`"
                class="pdf-iframe"
              />
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<style scoped>
.template-editor {
  display: flex;
  flex-direction: column;
  height: calc(100vh - 64px);
}

.editor-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 2rem;
  background: var(--va-background-element);
  border-bottom: 1px solid var(--va-background-border);
}

.header-left {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.page-title {
  font-size: 1.5rem;
  font-weight: 700;
  margin: 0;
}

.page-subtitle {
  font-size: 0.875rem;
  color: var(--va-text-secondary);
  margin: 0.25rem 0 0 0;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.editor-content {
  display: grid;
  grid-template-columns: 1fr 1fr;
  flex: 1;
  overflow: hidden;
}

.left-pane,
.right-pane {
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.left-pane {
  border-right: 1px solid var(--va-background-border);
}

.left-pane-header {
  min-height: 64px;
  display: flex;
  align-items: center;
  border-bottom: 1px solid var(--va-background-border);
}

.left-pane-header :deep(.va-tabs) {
  width: 100%;
}

.left-pane-header :deep(.va-tabs__tabs-wrapper) {
  padding: 0 1rem;
}

.tab-icon {
  margin-right: 0.5rem;
}

.tab-content {
  flex: 1;
  overflow: hidden;
}

.editor-tab {
  height: 100%;
}

.schema-builder-tab {
  overflow-y: auto;
}

.metadata-tab {
  padding: 2rem;
  overflow-y: auto;
  height: 100%;
}

.metadata-form {
  max-width: 600px;
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.switch-labels {
  display: flex;
  gap: 2rem;
  font-size: 0.875rem;
}

.preview-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 1.5rem;
  border-bottom: 1px solid var(--va-background-border);
  min-height: 64px;
}

.preview-title {
  font-size: 1rem;
  font-weight: 600;
  margin: 0;
}

.preview-content {
  flex: 1;
  overflow: hidden;
  position: relative;
}

.spec-preview,
.pdf-preview {
  height: 100%;
}

.pdf-iframe {
  width: 100%;
  height: 100%;
  border: none;
}

.loading-state,
.empty-state,
.error-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
  padding: 2rem;
  text-align: center;
  color: var(--va-text-secondary);
}

.loading-state p,
.empty-state p {
  margin-top: 1rem;
}

.error-state {
  color: var(--va-danger);
}

.error-state pre {
  margin-top: 1rem;
  padding: 1rem;
  background: var(--va-background-element);
  border-radius: 0.5rem;
  text-align: left;
  overflow: auto;
  max-width: 100%;
}

.text-sm {
  font-size: 0.875rem;
}

@media (max-width: 1200px) {
  .editor-content {
    grid-template-columns: 1fr;
    grid-template-rows: 1fr 1fr;
  }

  .left-pane {
    border-right: none;
    border-bottom: 1px solid var(--va-background-border);
  }
}
</style>
