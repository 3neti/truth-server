<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import yaml from 'js-yaml'

type Format = 'pdf' | 'html' | 'md'

const loading = ref(false)
const errorMsg = ref<string | null>(null)

const templates = ref<string[]>([])
const selectedTemplate = ref<string | null>(null)

const dataKind = ref<'json'|'yaml'>('json')
const dataText = ref<string>('{ "name": "World" }')

const format = ref<Format>('pdf')
const paperSize = ref<string>('A4')
const orientation = ref<'portrait'|'landscape'>('portrait')

const engineFlags = ref<any>({})     // optional helpers etc
const partials = ref<Record<string,string>>({}) // optional inline partials
const assetsBaseUrl = ref<string | null>(null)

const outputHtml = ref<string>('')   // for html/md preview
const outputMd = ref<string>('')     // for md preview

onMounted(async () => {
    try {
        const r = await fetch('/api/templates', { credentials: 'same-origin' })
        const j = await r.json()
        templates.value = j.templates ?? []
        if (!selectedTemplate.value && templates.value.length) {
            selectedTemplate.value = templates.value[0]
        }
    } catch (e: any) {
        errorMsg.value = 'Failed to load templates'
    }
})

function parseData(): any {
    const txt = dataText.value.trim()
    if (!txt) return {}
    try {
        return dataKind.value === 'json'
            ? JSON.parse(txt)
            : yaml.load(txt) ?? {}
    } catch (e: any) {
        throw new Error(`Data parse error (${dataKind.value}): ${e.message || e}`)
    }
}

async function onRender() {
    errorMsg.value = null
    outputHtml.value = ''
    outputMd.value = ''
    loading.value = true

    try {
        if (!selectedTemplate.value) throw new Error('Select a template')

        const body = {
            templateName: selectedTemplate.value,
            format: format.value,
            data: parseData(),
            schema: null,                // optionally pass a schema if you want server-side validation
            partials: Object.keys(partials.value).length ? partials.value : null,
            engineFlags: Object.keys(engineFlags.value).length ? engineFlags.value : {},
            paperSize: paperSize.value,
            orientation: orientation.value,
            assetsBaseUrl: assetsBaseUrl.value || null,
        }

        const r = await fetch('/api/render', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        })

        if (!r.ok) {
            const txt = await r.text()
            throw new Error(`Render failed: ${r.status} ${txt}`)
        }

        if (format.value === 'pdf') {
            const blob = await r.blob()
            const url = URL.createObjectURL(blob)
            // open in new tab + download link
            window.open(url, '_blank')
            // or trigger download:
            // const a = document.createElement('a')
            // a.href = url
            // a.download = 'render.pdf'
            // a.click()
            // URL.revokeObjectURL(url) // later
        } else if (format.value === 'html') {
            outputHtml.value = await r.text()
        } else if (format.value === 'md') {
            outputMd.value = await r.text()
        }
    } catch (e: any) {
        errorMsg.value = e.message || String(e)
    } finally {
        loading.value = false
    }
}

const canRender = computed(() => !!selectedTemplate.value && !loading.value)
</script>

<template>
    <div class="space-y-4">
        <h2 class="font-semibold text-lg">Truth Renderer</h2>

        <div v-if="errorMsg" class="p-2 rounded bg-red-50 text-red-700 border border-red-200">
            {{ errorMsg }}
        </div>

        <div class="grid gap-3 md:grid-cols-3">
            <div class="space-y-2">
                <label class="block text-sm font-medium">Template</label>
                <select v-model="selectedTemplate" class="w-full border rounded p-2">
                    <option v-for="t in templates" :key="t" :value="t">{{ t }}</option>
                </select>

                <label class="block text-sm font-medium mt-3">Format</label>
                <select v-model="format" class="w-full border rounded p-2">
                    <option value="pdf">PDF</option>
                    <option value="html">HTML</option>
                    <option value="md">Markdown</option>
                </select>

                <div class="grid grid-cols-2 gap-2 mt-3">
                    <div>
                        <label class="block text-sm font-medium">Paper</label>
                        <input v-model="paperSize" class="w-full border rounded p-2" placeholder="A4 / Letter" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Orientation</label>
                        <select v-model="orientation" class="w-full border rounded p-2">
                            <option value="portrait">portrait</option>
                            <option value="landscape">landscape</option>
                        </select>
                    </div>
                </div>

                <label class="block text-sm font-medium mt-3">Assets Base Path (optional)</label>
                <input v-model="assetsBaseUrl" class="w-full border rounded p-2" placeholder="/abs/path/for/dompdf" />
            </div>

            <div class="space-y-2 md:col-span-2">
                <div class="flex items-center gap-3">
                    <label class="text-sm font-medium">Data</label>
                    <label class="inline-flex items-center gap-1 text-xs">
                        <input type="radio" value="json" v-model="dataKind" /> JSON
                    </label>
                    <label class="inline-flex items-center gap-1 text-xs">
                        <input type="radio" value="yaml" v-model="dataKind" /> YAML
                    </label>
                    <button
                        class="ml-auto px-3 py-2 rounded bg-black text-white text-sm"
                        :disabled="!canRender"
                        @click="onRender"
                    >
                        {{ loading ? 'Rendering…' : 'Render' }}
                    </button>
                </div>

                <textarea
                    v-model="dataText"
                    class="w-full border rounded p-2 h-48 font-mono text-xs"
                    placeholder='{"name": "World"}'
                />
            </div>
        </div>

        <!-- Previews -->
        <div v-if="format === 'html' && outputHtml" class="border rounded">
            <iframe :srcdoc="outputHtml" class="w-full h-96"></iframe>
        </div>

        <div v-if="format === 'md' && outputMd" class="border rounded p-3 bg-gray-50 whitespace-pre-wrap font-mono text-sm">
            {{ outputMd }}
        </div>
    </div>
</template>

<style scoped>
/* minimal styles; tailor to your UI kit */
</style>
