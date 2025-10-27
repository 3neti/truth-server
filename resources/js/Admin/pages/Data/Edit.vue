<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from '../../layouts/AdminLayout.vue'
import MonacoEditor from '../../components/editors/MonacoEditor.vue'
import FormPreview from '../../components/schema/FormPreview.vue'
import { useTemplateDataStore } from '../../stores/templateData'
import { useTemplatesStore } from '../../stores/templates'
import { useRenderingJobsStore } from '../../stores/renderingJobs'
import type { Template } from '../../stores/templates'
import { debounce } from 'lodash-es'

const props = defineProps<{
  id?: number
}>()

const dataStore = useTemplateDataStore()
const templatesStore = useTemplatesStore()
const jobsStore = useRenderingJobsStore()

// Editor state
const activeTab = ref<'metadata' | 'form' | 'json'>('metadata')
const previewTab = ref<'spec' | 'pdf'>('spec')

const loading = ref(false)
const saving = ref(false)
const validating = ref(false)
const compiling = ref(false)
const rendering = ref(false)

// Form fields
const documentId = ref('')
const name = ref('')
const selectedTemplateId = ref<number | null>(null)
const templateRef = ref('')
const portableFormat = ref(false)
const jsonData = ref<Record<string, any>>({})

// Validation and preview
const validationErrors = ref<string[]>([])
const compiledSpec = ref<any>(null)
const renderedPdf = ref<string | null>(null)
const currentJobId = ref<number | null>(null)
const jobPollingInterval = ref<number | null>(null)

const isEditMode = computed(() => !!props.id)
const currentData = computed(() => dataStore.currentDataFile)
const pageTitle = computed(() => isEditMode.value ? `Edit: ${documentId.value}` : 'Create Data File')

const selectedTemplate = computed<Template | null>(() => {
  if (!selectedTemplateId.value) return null
  return templatesStore.templates.find(t => t.id === selectedTemplateId.value) || null
})

const jsonSchema = computed(() => {
  if (!selectedTemplate.value?.json_schema) return null
  try {
    return typeof selectedTemplate.value.json_schema === 'string'
      ? JSON.parse(selectedTemplate.value.json_schema)
      : selectedTemplate.value.json_schema
  } catch {
    return null
  }
})

// Formatted JSON for editor
const jsonDataString = computed({
  get: () => JSON.stringify(jsonData.value, null, 2),
  set: (value: string) => {
    try {
      jsonData.value = JSON.parse(value)
    } catch (e) {
      // Keep invalid JSON as is
    }
  }
})

const compiledSpecJson = computed(() =>
  compiledSpec.value ? JSON.stringify(compiledSpec.value, null, 2) : ''
)

// Watch template selection to sync template_ref and initialize form fields
watch(selectedTemplateId, (newTemplateId, oldTemplateId) => {
  if (newTemplateId) {
    const template = templatesStore.templates.find(t => t.id === newTemplateId)
    if (template) {
      // Auto-populate template_ref when template is selected
      // Format: family-slug/template-name or just the category
      if (template.family?.slug && template.layout_variant) {
        templateRef.value = `local:${template.family.slug}/${template.layout_variant}`
      } else if (template.category) {
        templateRef.value = `local:${template.category}/${template.layout_variant || 'standard'}`
      }

      // Initialize with default values from schema for new records
      if (!isEditMode.value && jsonSchema.value) {
        jsonData.value = initializeFromSchema(jsonSchema.value)
      }
    }
  }
})

onMounted(async () => {
  loading.value = true

  // Load templates first so dropdown is populated
  await templatesStore.fetchTemplates()

  if (props.id) {
    const fetchedData = await dataStore.fetchDataFile(props.id)

    if (fetchedData) {
      documentId.value = fetchedData.document_id
      name.value = fetchedData.name || ''
      templateRef.value = fetchedData.template_ref || ''
      portableFormat.value = fetchedData.portable_format || false

      // Set template_id - ensure it's a number if exists
      if (fetchedData.template_id) {
        selectedTemplateId.value = typeof fetchedData.template_id === 'string'
          ? parseInt(fetchedData.template_id)
          : fetchedData.template_id
      } else if (fetchedData.template_ref) {
        // If no template_id but has template_ref, try to find matching template
        // template_ref format: "local:family-slug/variant" e.g., "local:election-ballot/standard"
        const matchingTemplate = templatesStore.templates.find(t => {
          if (t.family?.slug && t.layout_variant) {
            const constructedRef = `local:${t.family.slug}/${t.layout_variant}`
            return constructedRef === fetchedData.template_ref
          }
          return false
        })

        if (matchingTemplate) {
          selectedTemplateId.value = matchingTemplate.id
        }
      }

      // Load JSON data
      if (fetchedData.json_data) {
        jsonData.value = typeof fetchedData.json_data === 'string'
          ? JSON.parse(fetchedData.json_data)
          : fetchedData.json_data
      }
    }
  }

  loading.value = false
})

function initializeFromSchema(schema: any): Record<string, any> {
  const data: Record<string, any> = {}

  if (schema.type === 'object' && schema.properties) {
    for (const [key, prop] of Object.entries(schema.properties as Record<string, any>)) {
      if (prop.default !== undefined) {
        data[key] = prop.default
      } else if (prop.type === 'string') {
        data[key] = ''
      } else if (prop.type === 'number' || prop.type === 'integer') {
        data[key] = 0
      } else if (prop.type === 'boolean') {
        data[key] = false
      } else if (prop.type === 'array') {
        data[key] = []
      } else if (prop.type === 'object') {
        data[key] = {}
      }
    }
  }

  return data
}

async function handleSave() {
  if (!documentId.value.trim()) {
    alert('Document ID is required')
    return
  }

  if (!selectedTemplateId.value && !templateRef.value.trim()) {
    alert('Please select a template or provide a template reference')
    return
  }

  saving.value = true

  try {
    const payload = {
      document_id: documentId.value,
      name: name.value || null,
      template_id: selectedTemplateId.value,
      template_ref: templateRef.value || null,
      portable_format: portableFormat.value,
      json_data: jsonData.value,
    }

    if (isEditMode.value && props.id) {
      await dataStore.updateDataFile(props.id, payload)
    } else {
      await dataStore.createDataFile(payload)
    }

    router.visit('/admin/data')
  } catch (error) {
    console.error('Failed to save data:', error)
    alert('Failed to save data file. Please check the console for details.')
  } finally {
    saving.value = false
  }
}

async function handleValidate() {
  if (!props.id) {
    alert('Please save the data file first')
    return
  }

  validating.value = true
  validationErrors.value = []

  try {
    const result = await dataStore.validateDataFile(props.id)

    if (result.valid) {
      alert('✓ Data is valid!')
    } else {
      validationErrors.value = result.errors || ['Validation failed']
    }
  } catch (error: any) {
    validationErrors.value = [error.message || 'Validation failed']
  } finally {
    validating.value = false
  }
}

async function handleCompile() {
  // Can compile without saving if we have template and data
  if (!selectedTemplate.value) {
    alert('Please select a template first')
    return
  }

  if (!jsonData.value || Object.keys(jsonData.value).length === 0) {
    alert('Please provide JSON data')
    return
  }

  compiling.value = true
  compiledSpec.value = null
  validationErrors.value = []

  try {
    // Compile template with data using the templates store
    const result = await templatesStore.compileTemplate(
      selectedTemplate.value.handlebars_template,
      jsonData.value
    )

    if (result.success) {
      compiledSpec.value = result.spec
      previewTab.value = 'spec'
    } else {
      throw new Error(result.error || 'Compilation failed')
    }
  } catch (error: any) {
    console.error('Compilation failed:', error)
    const errorMsg = error.response?.data?.error || error.message || 'Failed to compile template'
    validationErrors.value = [errorMsg]
    alert(errorMsg)
  } finally {
    compiling.value = false
  }
}

async function handleRender() {
  // Compile first if we don't have a compiled spec
  if (!compiledSpec.value) {
    await handleCompile()
    if (!compiledSpec.value) return
  }
  
  rendering.value = true
  renderedPdf.value = null
  validationErrors.value = []
  
  try {
    // Create a rendering job instead of direct render
    const job = await jobsStore.createJob({
      template_data_id: props.id,
      spec: compiledSpec.value,
    })
    
    currentJobId.value = job.id
    previewTab.value = 'pdf'
    
    // Start polling for job completion
    startJobPolling(job.id)
    
  } catch (error: any) {
    console.error('Render failed:', error)
    const errorMsg = error.response?.data?.error || error.message || 'Failed to create rendering job'
    validationErrors.value = [errorMsg]
    alert(errorMsg)
    rendering.value = false
  }
}

function startJobPolling(jobId: number) {
  // Clear any existing polling
  stopJobPolling()
  
  // Poll every 2 seconds
  jobPollingInterval.value = window.setInterval(async () => {
    try {
      const job = await jobsStore.fetchJob(jobId)
      
      if (job.status === 'completed') {
        // Job finished successfully
        if (job.pdf_url) {
          renderedPdf.value = job.pdf_url
        }
        rendering.value = false
        stopJobPolling()
      } else if (job.status === 'failed') {
        // Job failed
        const errorMsg = job.error_message || 'Rendering failed'
        validationErrors.value = [errorMsg]
        rendering.value = false
        stopJobPolling()
      } else if (job.status === 'cancelled') {
        // Job was cancelled
        validationErrors.value = ['Rendering was cancelled']
        rendering.value = false
        stopJobPolling()
      }
      // If pending or processing, keep polling
    } catch (error) {
      console.error('Error polling job:', error)
      // Continue polling even on error
    }
  }, 2000)
}

function stopJobPolling() {
  if (jobPollingInterval.value) {
    clearInterval(jobPollingInterval.value)
    jobPollingInterval.value = null
  }
}

function handleCancel() {
  stopJobPolling()
  router.visit('/admin/data')
}

// Cleanup on unmount
import { onUnmounted } from 'vue'

onUnmounted(() => {
  stopJobPolling()
})

// Schema-based form field generator
function getFieldType(schema: any): string {
  if (schema.enum) return 'select'
  if (schema.type === 'boolean') return 'checkbox'
  if (schema.type === 'integer' || schema.type === 'number') return 'number'
  if (schema.type === 'string' && schema.format === 'date') return 'date'
  if (schema.type === 'string' && schema.format === 'date-time') return 'datetime'
  if (schema.type === 'string' && schema.maxLength && schema.maxLength > 200) return 'textarea'
  return 'text'
}
</script>

<template>
  <AdminLayout>
    <div class="data-editor">
      <!-- Header -->
      <div class="page-header">
        <div class="header-left">
          <h1 class="page-title">{{ pageTitle }}</h1>
          <p class="page-subtitle" v-if="selectedTemplate">
            Template: {{ selectedTemplate.name }}
          </p>
        </div>
        <div class="header-actions">
          <va-button preset="secondary" @click="handleCancel" :disabled="saving">
            Cancel
          </va-button>
          <va-button color="primary" @click="handleSave" :loading="saving">
            {{ isEditMode ? 'Save' : 'Create' }}
          </va-button>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="loading-container">
        <va-progress-circle indeterminate />
        <p>Loading...</p>
      </div>

      <!-- Split Editor Layout -->
      <div v-else class="split-editor">
        <!-- Left Pane: Tabs -->
        <va-card class="left-pane">
          <va-card-title>
            <va-tabs v-model="activeTab" class="editor-tabs">
              <va-tab name="metadata">Metadata</va-tab>
              <va-tab name="form" :disabled="!jsonSchema">
                <va-icon name="list_alt" class="tab-icon" />
                Form
              </va-tab>
              <va-tab name="json">JSON Data</va-tab>
            </va-tabs>
          </va-card-title>
          <va-card-content class="editor-content-area">
            <!-- Metadata Tab -->
            <div v-show="activeTab === 'metadata'" class="tab-panel">
              <div class="form-fields">
                <va-input
                  v-model="documentId"
                  label="Document ID *"
                  placeholder="e.g., DOC-001"
                  :disabled="isEditMode"
                  class="field"
                />

                <va-input
                  v-model="name"
                  label="Name"
                  placeholder="Optional friendly name"
                  class="field"
                />

                <va-select
                  v-model="selectedTemplateId"
                  :options="templatesStore.templates"
                  label="Template *"
                  placeholder="Select a template"
                  text-by="name"
                  value-by="id"
                  class="field"
                >
                  <template #content="{ value }">
                    <div v-if="value">
                      <div>{{ value.name }}</div>
                      <div v-if="value.family" class="text-sm text-muted">
                        {{ value.family.name }}
                      </div>
                    </div>
                  </template>
                </va-select>

                <va-input
                  v-model="templateRef"
                  label="Template Reference"
                  placeholder="Auto-generated from template selection"
                  :disabled="!!selectedTemplateId"
                  class="field"
                >
                  <template #message v-if="selectedTemplateId">
                    Auto-populated from selected template
                  </template>
                </va-input>

                <va-checkbox
                  v-model="portableFormat"
                  label="Portable Format"
                  class="field"
                />
              </div>
            </div>

            <!-- Form Tab -->
            <div v-show="activeTab === 'form'" class="tab-panel">
              <div v-if="!jsonSchema" class="empty-form-state">
                <va-icon name="info" size="large" color="secondary" />
                <p>No schema available for this template</p>
                <p class="text-sm">Select a template with a JSON schema to use form mode</p>
              </div>
              <FormPreview
                v-else
                :schema="jsonSchema"
                v-model="jsonData"
              />
            </div>

            <!-- JSON Data Tab -->
            <div v-show="activeTab === 'json'" class="tab-panel">
              <MonacoEditor
                v-model="jsonDataString"
                language="json"
                :height="600"
              />
            </div>
          </va-card-content>
        </va-card>

        <!-- Right Pane: Preview -->
        <va-card class="right-pane">
          <va-card-title>
            <div class="preview-header">
              <va-tabs v-model="previewTab" class="preview-tabs">
                <va-tab name="spec">Compiled Spec</va-tab>
                <va-tab name="pdf">PDF Preview</va-tab>
              </va-tabs>
              <div class="preview-actions">
                <va-button
                  size="small"
                  preset="secondary"
                  icon="refresh"
                  :loading="compiling"
                  @click="handleCompile"
                  title="Compile"
                >
                  Compile
                </va-button>
                <va-button
                  size="small"
                  preset="secondary"
                  icon="picture_as_pdf"
                  :loading="rendering"
                  @click="handleRender"
                  title="Render PDF"
                >
                  Render
                </va-button>
              </div>
            </div>
          </va-card-title>
          <va-card-content class="preview-content-area">
            <!-- Compiled Spec Preview -->
            <div v-show="previewTab === 'spec'" class="preview-panel">
              <div v-if="compiling" class="preview-loading">
                <va-progress-circle indeterminate size="small" />
                <p>Compiling...</p>
              </div>
              <div v-else-if="compiledSpec" class="preview-result">
                <MonacoEditor
                  :model-value="compiledSpecJson"
                  language="json"
                  :height="600"
                  :options="{ readOnly: true }"
                />
              </div>
              <div v-else class="preview-empty">
                <va-icon name="code" size="large" />
                <p>Click "Compile" to generate spec</p>
              </div>
            </div>

            <!-- PDF Preview -->
            <div v-show="previewTab === 'pdf'" class="preview-panel">
              <div v-if="rendering" class="preview-loading">
                <va-progress-circle indeterminate size="small" />
                <p>Rendering PDF...</p>
              </div>
              <div v-else-if="renderedPdf" class="preview-result">
                <iframe :src="renderedPdf" class="pdf-frame" />
              </div>
              <div v-else class="preview-empty">
                <va-icon name="picture_as_pdf" size="large" />
                <p>Click "Render" to generate PDF</p>
              </div>
            </div>

            <!-- Validation Errors -->
            <div v-if="validationErrors.length > 0" class="error-banner">
              <va-icon name="error" color="danger" />
              <div class="error-content">
                <strong>Validation Errors:</strong>
                <ul>
                  <li v-for="(error, idx) in validationErrors" :key="idx">
                    {{ error }}
                  </li>
                </ul>
              </div>
            </div>
          </va-card-content>
        </va-card>
      </div>
    </div>
  </AdminLayout>
</template>

<style scoped>
.data-editor {
  height: calc(100vh - 2rem);
  display: flex;
  flex-direction: column;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem 0;
  border-bottom: 1px solid var(--va-background-border);
  margin-bottom: 1.5rem;
}

.header-left {
  flex: 1;
}

.page-title {
  font-size: 1.5rem;
  font-weight: 600;
  margin: 0;
}

.page-subtitle {
  font-size: 0.875rem;
  color: var(--va-text-secondary);
  margin: 0.25rem 0 0 0;
}

.header-actions {
  display: flex;
  gap: 0.75rem;
}

.loading-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  flex: 1;
  color: var(--va-text-secondary);
}

.loading-container p {
  margin-top: 1rem;
}

.split-editor {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.5rem;
  flex: 1;
  min-height: 0;
}

.left-pane,
.right-pane {
  display: flex;
  flex-direction: column;
  min-height: 0;
}

.editor-tabs,
.preview-tabs {
  flex: 1;
}

.editor-content-area {
  flex: 1;
  min-height: 0;
  overflow: auto;
}

.preview-content-area {
  flex: 1;
  min-height: 800px;
  overflow: auto;
}

.tab-panel {
  height: 100%;
}

.form-fields {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  padding: 1rem 0;
}

.field {
  margin: 0;
}

.preview-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 100%;
}

.preview-actions {
  display: flex;
  gap: 0.5rem;
}

.preview-panel {
  height: 100%;
  display: flex;
  flex-direction: column;
}

.preview-loading,
.preview-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  flex: 1;
  color: var(--va-text-secondary);
  padding: 3rem;
  text-align: center;
}

.preview-loading p,
.preview-empty p {
  margin-top: 1rem;
}

.preview-result {
  flex: 1;
  min-height: 0;
}

.pdf-frame {
  width: 100%;
  height: 100%;
  min-height: 800px;
  border: none;
}

.error-banner {
  display: flex;
  gap: 0.75rem;
  padding: 1rem;
  background: var(--va-danger-lightest);
  border: 1px solid var(--va-danger);
  border-radius: 4px;
  margin-top: 1rem;
}

.error-content {
  flex: 1;
}

.error-content ul {
  margin: 0.5rem 0 0 1.5rem;
  padding: 0;
}

.error-content li {
  margin-bottom: 0.25rem;
}

.text-sm {
  font-size: 0.75rem;
}

.text-muted {
  color: var(--va-text-secondary);
}

.tab-icon {
  margin-right: 0.5rem;
}

.empty-form-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem;
  color: var(--va-text-secondary);
  text-align: center;
}

.empty-form-state p {
  margin-top: 1rem;
}

@media (max-width: 1400px) {
  .split-editor {
    grid-template-columns: 1fr;
    grid-template-rows: 1fr 1fr;
  }
}
</style>
