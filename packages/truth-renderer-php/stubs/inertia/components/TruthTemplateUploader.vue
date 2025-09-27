<script setup lang="ts">
import { ref, computed } from 'vue'

type Mode = 'zip' | 'manual'

const mode = ref<Mode>('zip')
const namespace_ = ref('core')
const slug = ref('invoice/basic') // example
const dryRun = ref(true)
const overwrite = ref(false)

// ZIP
const zipFile = ref<File | null>(null)

// Manual
const tplText = ref('')
const tplFile = ref<File | null>(null)

const schemaText = ref('')
const schemaFile = ref<File | null>(null)

const partialFiles = ref<FileList | null>(null)

const busy = ref(false)
const result = ref<any>(null)
const errorMsg = ref<string | null>(null)

const canSubmit = computed(() => {
    if (!slug.value.trim()) return false
    if (mode.value === 'zip') return !!zipFile.value
    // manual
    return !!tplFile.value || !!tplText.value.trim()
})

function onZipPicked(e: Event) {
    const input = e.target as HTMLInputElement
    zipFile.value = input.files?.[0] || null
}

function onTplPicked(e: Event) {
    const input = e.target as HTMLInputElement
    tplFile.value = input.files?.[0] || null
}

function onSchemaPicked(e: Event) {
    const input = e.target as HTMLInputElement
    schemaFile.value = input.files?.[0] || null
}

function onPartialsPicked(e: Event) {
    const input = e.target as HTMLInputElement
    partialFiles.value = input.files || null
}

async function upload() {
    errorMsg.value = null
    result.value = null
    busy.value = true

    try {
        const form = new FormData()
        form.append('namespace', namespace_.value)
        form.append('slug', slug.value)
        form.append('dryRun', String(dryRun.value))
        form.append('overwrite', String(overwrite.value))

        if (mode.value === 'zip') {
            if (zipFile.value) form.append('zip', zipFile.value)
        } else {
            if (tplFile.value) {
                form.append('template', tplFile.value)
            } else {
                form.append('template', tplText.value)
            }
            if (schemaFile.value) {
                form.append('schema', schemaFile.value)
            } else if (schemaText.value.trim()) {
                try {
                    // Validate it's JSON
                    JSON.parse(schemaText.value)
                    form.append('schema', schemaText.value)
                } catch {
                    errorMsg.value = 'Schema is not valid JSON'
                    busy.value = false
                    return
                }
            }
            if (partialFiles.value?.length) {
                for (let i = 0; i < partialFiles.value.length; i++) {
                    form.append('partials[]', partialFiles.value.item(i)!)
                }
            }
        }

        const res = await fetch('/truth/templates/upload', {
            method: 'POST',
            body: form,
            headers: {
                // Let the browser set multipart boundary automatically
            },
        })

        const json = await res.json().catch(() => ({}))
        if (!res.ok) {
            errorMsg.value = json?.error || `Upload failed (HTTP ${res.status})`
            return
        }
        result.value = json
    } catch (e: any) {
        errorMsg.value = e?.message || String(e)
    } finally {
        busy.value = false
    }
}
</script>

<template>
    <div class="border rounded p-4 space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold">Template Uploader</h2>
            <span class="text-xs text-gray-500">{{ busy ? 'Uploading…' : 'Idle' }}</span>
        </div>

        <!-- Namespace + Slug -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div>
                <label class="block text-sm font-medium">Namespace</label>
                <input v-model="namespace_" class="w-full border rounded p-2" placeholder="core" />
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium">Slug</label>
                <input v-model="slug" class="w-full border rounded p-2" placeholder="invoice/basic" />
                <p class="text-xs text-gray-500 mt-1">
                    Final path: <code>resources/truth-templates/{{ namespace_ }}/{{ slug }}</code>
                </p>
            </div>
        </div>

        <!-- Options -->
        <div class="flex items-center gap-4 text-sm">
            <label class="inline-flex items-center gap-2">
                <input type="checkbox" v-model="dryRun" />
                Dry run compile
            </label>
            <label class="inline-flex items-center gap-2">
                <input type="checkbox" v-model="overwrite" />
                Overwrite if exists
            </label>
        </div>

        <!-- Mode tabs -->
        <div class="flex gap-2">
            <button
                class="px-3 py-1 rounded text-sm"
                :class="mode==='zip' ? 'bg-black text-white' : 'bg-gray-200'"
                @click="mode='zip'"
            >ZIP</button>
            <button
                class="px-3 py-1 rounded text-sm"
                :class="mode==='manual' ? 'bg-black text-white' : 'bg-gray-200'"
                @click="mode='manual'"
            >Manual</button>
        </div>

        <!-- ZIP mode -->
        <div v-if="mode==='zip'" class="space-y-2">
            <label class="block text-sm font-medium">ZIP file (template.hbs / schema.json / partials/*.hbs)</label>
            <input type="file" accept=".zip" @change="onZipPicked" />
            <p class="text-xs text-gray-500">We’ll extract and validate contents before finalizing.</p>
        </div>

        <!-- Manual mode -->
        <div v-else class="space-y-4">
            <div>
                <label class="block text-sm font-medium">Main template</label>
                <div class="flex items-center gap-2">
                    <input type="file" accept=".hbs,.html" @change="onTplPicked" />
                    <span class="text-xs text-gray-500">or paste:</span>
                </div>
                <textarea v-model="tplText" class="w-full border rounded p-2 h-28 font-mono text-xs" placeholder="template.hbs source"></textarea>
            </div>

            <div>
                <label class="block text-sm font-medium">Schema (optional)</label>
                <div class="flex items-center gap-2">
                    <input type="file" accept=".json" @change="onSchemaPicked" />
                    <span class="text-xs text-gray-500">or paste:</span>
                </div>
                <textarea v-model="schemaText" class="w-full border rounded p-2 h-24 font-mono text-xs" placeholder='{"type":"object"}'></textarea>
            </div>

            <div>
                <label class="block text-sm font-medium">Partials (optional)</label>
                <input type="file" accept=".hbs" multiple @change="onPartialsPicked" />
                <p class="text-xs text-gray-500">Files will be placed under <code>partials/</code></p>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-3">
            <button
                class="px-4 py-2 rounded text-sm"
                :class="canSubmit ? 'bg-black text-white' : 'bg-gray-300 text-gray-600 cursor-not-allowed'"
                :disabled="!canSubmit || busy"
                @click="upload"
            >
                {{ busy ? 'Uploading…' : 'Upload' }}
            </button>
        </div>

        <!-- Error -->
        <div v-if="errorMsg" class="p-3 bg-red-50 border border-red-200 text-red-800 text-sm rounded">
            {{ errorMsg }}
        </div>

        <!-- Result -->
        <div v-if="result" class="p-3 bg-green-50 border border-green-200 text-green-800 text-sm rounded space-y-1">
            <div><strong>Uploaded!</strong></div>
            <div>templateName: <code>{{ result.templateName }}</code></div>
            <div v-if="result.files?.length">files:
                <ul class="list-disc pl-6">
                    <li v-for="p in result.files" :key="p"><code>{{ p }}</code></li>
                </ul>
            </div>
            <!-- Quick render link could go here -->
        </div>
    </div>
</template>
