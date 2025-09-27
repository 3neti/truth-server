<script setup lang="ts">
import { ref, computed } from 'vue'

type Mode = 'zip' | 'manual'

const mode = ref<Mode>('zip')
const namespace_ = ref('core')
const slug = ref('invoice/basic')
const dryRun = ref(true)
const overwrite = ref(false)

const zipFile = ref<File | null>(null)
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
    return mode.value === 'zip' ? !!zipFile.value : !!tplFile.value || !!tplText.value.trim()
})

function onPicked(e: Event, type: string) {
    const file = (e.target as HTMLInputElement).files?.[0] || null
    switch (type) {
        case 'zip': zipFile.value = file; break
        case 'tpl': tplFile.value = file; break
        case 'schema': schemaFile.value = file; break
    }
}
function onPartialsPicked(e: Event) {
    partialFiles.value = (e.target as HTMLInputElement).files
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
            if (tplFile.value) form.append('template', tplFile.value)
            else form.append('template', tplText.value)

            if (schemaFile.value) form.append('schema', schemaFile.value)
            else if (schemaText.value.trim()) form.append('schema', schemaText.value)

            if (partialFiles.value?.length) {
                for (let i = 0; i < partialFiles.value.length; i++) {
                    form.append('partials[]', partialFiles.value.item(i)!)
                }
            }
        }

        const res = await fetch('/truth/templates/upload', { method: 'POST', body: form })
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
    <div class="space-y-4 p-6 bg-white rounded shadow max-w-2xl mx-auto">
        <h2 class="text-lg font-semibold">Template Uploader</h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div>
                <label class="text-sm font-medium">Namespace</label>
                <input v-model="namespace_" class="border border-gray-300 rounded px-2 py-1 w-full" placeholder="core" />
            </div>
            <div class="md:col-span-2">
                <label class="text-sm font-medium">Slug</label>
                <input v-model="slug" class="border border-gray-300 rounded px-2 py-1 w-full" placeholder="invoice/basic" />
                <p class="text-xs text-gray-500 mt-1">
                    Will be stored under: <code>resources/truth-templates/{{ namespace_ }}/{{ slug }}</code>
                </p>
            </div>
        </div>

        <div class="flex items-center gap-4">
            <label><input type="checkbox" v-model="dryRun" class="mr-1" /> Dry-run compile</label>
            <label><input type="checkbox" v-model="overwrite" class="mr-1" /> Overwrite existing</label>
        </div>

        <div class="flex gap-2">
            <button @click="mode = 'zip'" :class="mode==='zip' ? 'px-3 py-1 text-sm rounded bg-black text-white' : 'px-3 py-1 text-sm rounded bg-gray-200 text-gray-700'">ZIP</button>
            <button @click="mode = 'manual'" :class="mode==='manual' ? 'px-3 py-1 text-sm rounded bg-black text-white' : 'px-3 py-1 text-sm rounded bg-gray-200 text-gray-700'">Manual</button>
        </div>

        <div v-if="mode==='zip'">
            <label class="text-sm font-medium">Upload ZIP file:</label>
            <input type="file" accept=".zip" @change="e => onPicked(e, 'zip')" />
        </div>

        <div v-else class="space-y-4">
            <div>
                <label class="text-sm font-medium">Template</label>
                <input type="file" accept=".hbs,.html" @change="e => onPicked(e, 'tpl')" />
                <textarea v-model="tplText" class="w-full border border-gray-300 rounded px-2 py-1 mt-2 text-sm font-mono h-32" placeholder="Paste template content here"></textarea>
            </div>

            <div>
                <label class="text-sm font-medium">Schema (optional)</label>
                <input type="file" accept=".json" @change="e => onPicked(e, 'schema')" />
                <textarea v-model="schemaText" class="w-full border border-gray-300 rounded px-2 py-1 mt-2 text-sm font-mono h-24" placeholder='{"type":"object"}'></textarea>
            </div>

            <div>
                <label class="text-sm font-medium">Partials (optional)</label>
                <input type="file" accept=".hbs" multiple @change="onPartialsPicked" />
            </div>
        </div>

        <div class="flex justify-end">
            <button :disabled="!canSubmit || busy" @click="upload" class="px-4 py-2 bg-black text-white rounded text-sm disabled:opacity-50">
                {{ busy ? 'Uploading…' : 'Upload' }}
            </button>
        </div>

        <div v-if="errorMsg" class="text-red-600 text-sm">{{ errorMsg }}</div>

        <div v-if="result" class="bg-green-50 border border-green-200 text-green-800 text-sm p-3 rounded">
            <p><strong>Success:</strong> {{ result.templateName }}</p>
            <ul v-if="result.files">
                <li v-for="f in result.files" :key="f"><code>{{ f }}</code></li>
            </ul>
        </div>
    </div>
</template>
