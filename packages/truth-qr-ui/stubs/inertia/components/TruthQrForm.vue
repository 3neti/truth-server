<script setup lang="ts">
import { ref } from 'vue'
import { parseIndexTotal } from '../composables/MultiPartTools'

// Existing composables
import usePayloadJson from '../composables/usePayloadJson'
import useWriterSpec from '../composables/useWriterSpec'
import useEncodeDecode from '../composables/useEncodeDecode'
import usePartsList from '../composables/usePartsList'
import useDownloads from '../composables/useDownloads'
import useQrGallery from '../composables/useQrGallery'

// NEW: scanner pieces
import ScannerPanel from './ScannerPanel.vue'

import { useRenderer } from '../composables/useRenderer'
import { useTemplateRegistry } from '../composables/useTemplateRegistry'

type AnyObject = Record<string, any>

/** ------------------------------
 * Form (non-payload, non-writer)
 * ------------------------------ */
const form = ref({
    envelope: 'v1url',
    prefix: 'TRUTH',
    version: 'v1',
    transport: 'base64url+deflate',
    serializer: 'json',
    by: 'size' as 'size' | 'count',
    size: 120,
    count: 3,
    templateName: 'core:invoice/basic/template'
})

/** ------------------------------
 * Payload JSON editor
 * ------------------------------ */
const {
    rawPayload,
    payload,
    payloadError,
    onPayloadInput,
    setPayload,
} = usePayloadJson({"code":"INV-20250904-001","date":"2025-09-04","items":[{"name":"USB-C Charging Cable","qty":2,"price":450.75},{"name":"Wireless Mouse","qty":1,"price":799.5},{"name":"Bluetooth Headphones","qty":1,"price":2399.99}],"total":4100.99} )

/** ------------------------------
 * Writer controls & spec builder
 * ------------------------------ */
const {
    include_qr,
    writer,
    writerFmt,
    writerSize,
    writerMargin,
    isWriterEnabled,
    buildWriterSpec,
} = useWriterSpec()

/** ------------------------------
 * Encode/Decode API bridge
 * ------------------------------ */
const {
    encodeWith,
    decodeWith,
    encodeResult,
    decodeResult,
    loading,
    error,
} = useEncodeDecode()

/** ------------------------------
 * Parts utilities & downloads
 * ------------------------------ */
const { listItems } = usePartsList()
const { downloadAllLines, downloadQrAssets } = useDownloads()

function onEncode() {
    if (payloadError.value) return
    const spec = buildWriterSpec()
    return encodeWith({
        payload: payload.value,
        code: (payload.value as AnyObject)?.code,
        envelope: form.value.envelope as any,
        prefix: form.value.prefix,
        version: form.value.version,
        transport: form.value.transport,
        serializer: form.value.serializer,
        by: form.value.by as any,
        size: form.value.size,
        count: form.value.count,
        include_qr: include_qr.value,
        writer: spec,
    } as any)
}

function onDecode() {
    const lines = (encodeResult.value?.urls ?? encodeResult.value?.lines ?? []) as string[]
    if (!lines.length) return
    return decodeWith(
        {
            payload: null,
            code: undefined,
            envelope: form.value.envelope as any,
            prefix: form.value.prefix,
            version: form.value.version,
            transport: form.value.transport,
            serializer: form.value.serializer,
        } as any,
        lines,
    )
}

/** ------------------------------
 * Downloads
 * ------------------------------ */
function downloadAll() {
    const items = listItems(encodeResult.value)
    if (!items.length) return
    const code = (encodeResult.value?.code ?? (payload.value as AnyObject)?.code) as string | undefined
    downloadAllLines(code, items)
}

function downloadQr() {
    const code = (encodeResult.value?.code ?? (payload.value as AnyObject)?.code) as string | undefined
    downloadQrAssets(code, encodeResult.value?.qr)
}

/** ------------------------------
 * QR Gallery Pagination (default 3×3 → 9/page)
 * ------------------------------ */
const { perPage, page, qrItems, totalPages, pagedQrs, prevPage, nextPage } = useQrGallery(encodeResult, 9)

/** ------------------------------
 * Scanner wiring (NEW)
 * ------------------------------ */
const scannerEl = ref<InstanceType<typeof ScannerPanel> | null>(null)
const active = ref(false)            // reflect child's camera state
const lastScan = ref('')             // latest decoded text from child
const scannedLines = ref<string[]>([])
const simulateMissing = ref(false)

function startCamera() {
    console.log('[TruthQrForm] startCamera() clicked')
    scannerEl.value?.start?.()
}

function stopCamera() {
    console.log('[TruthQrForm] stopCamera() clicked')
    scannerEl.value?.stop?.()
}

function onDetected(s: string) {
    console.log('[TruthQrForm] onDetected payload:', s)
    lastScan.value = (s || '').trim()
}

function onStarted() {
    console.log('[TruthQrForm] onStarted (camera active)')
    active.value = true
}

function onStopped() {
    console.log('[TruthQrForm] onStopped (camera inactive)')
    active.value = false
}

function addLastScan() {
    const s = lastScan.value?.trim()
    if (!s) return
    if (!scannedLines.value.includes(s)) scannedLines.value.push(s)
}

function clearScanned() {
    scannedLines.value = []
    resetScan()
}

function scannedProgress() {
    // quick inferred progress from headers
    let total = 0
    let got = 0
    for (const ln of scannedLines.value) {
        const meta = parseIndexTotal(ln)
        if (meta) {
            total = Math.max(total, meta.n)
            got += 1
        }
    }
    return { received: got, total }
}

function decodeScanned() {
    if (!scannedLines.value.length) return
    let lines = [...scannedLines.value]
    if (simulateMissing.value) {
        const meta = parseIndexTotal(lines[0] || '')
        if (meta && meta.n > 2) {
            const miss = Math.ceil(meta.n / 2)
            lines = lines.filter((ln) => parseIndexTotal(ln)?.i !== miss)
        }
    }

    return decodeWith(
        {
            payload: null,
            code: undefined,
            envelope: form.value.envelope as any,
            prefix: form.value.prefix,
            version: form.value.version,
            transport: form.value.transport,
            serializer: form.value.serializer,
        } as any,
        lines,
    )
}

// Provide ScannerPanel the decode args it needs (envelope/prefix/version/…)
function getDecodeArgs() {
    return {
        envelope: form.value.envelope as 'v1url' | 'v1line',
        prefix: form.value.prefix,
        version: form.value.version,
        transport: form.value.transport,
        serializer: form.value.serializer,
    }
}

const { render } = useRenderer()
const selectedTemplate = ref('core:invoice/basic/template')

async function handleDownload() {
    if (!rawPayload.value?.trim()) {
        alert('❌ Payload is empty or invalid.')
        return
    }

    let parsed
    try {
        parsed = JSON.parse(rawPayload.value)
    } catch (err) {
        console.error('❌ Failed to parse payload:', err)
        alert('❌ Invalid JSON payload.')
        return
    }

    await render({
        templateName: form.value.templateName,
        format: 'pdf',
        filename: 'truth-result',
        download: true,
        data: parsed,
        engineFlags: {
            strict: true,
        },
    })
}

async function handlePreview() {
    if (!rawPayload.value?.trim()) {
        alert('❌ Payload is empty or invalid.')
        return
    }

    let parsed
    try {
        parsed = JSON.parse(rawPayload.value)
    } catch (err) {
        console.error('❌ Failed to parse payload:', err)
        alert('❌ Invalid JSON payload.')
        return
    }

    await render({
        templateName: form.value.templateName,
        format: 'html',
        filename: 'truth-result',
        openInNewTab: true,
        data: parsed,
        engineFlags: {
            strict: true,
        },
    })
}

const { templates } = useTemplateRegistry()

</script>

<template>
    <div class="space-y-6">
        <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium">Envelope</label>
                <select v-model="form.envelope" class="w-full border rounded p-2">
                    <option value="v1url">v1url</option>
                    <option value="v1line">v1line</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium">Prefix</label>
                <input v-model="form.prefix" class="w-full border rounded p-2" placeholder="TRUTH / ER / …" />
            </div>

            <div>
                <label class="block text-sm font-medium">Version</label>
                <input v-model="form.version" class="w-full border rounded p-2" placeholder="v1 / v2 / …" />
            </div>

            <div>
                <label class="block text-sm font-medium">Transport</label>
                <select v-model="form.transport" class="w-full border rounded p-2">
                    <option value="base64url+deflate">base64url+deflate</option>
                    <option value="base64url+gzip">base64url+gzip</option>
                    <option value="base64url">base64url</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium">Serializer</label>
                <select v-model="form.serializer" class="w-full border rounded p-2">
                    <option value="json">json</option>
                    <option value="yaml">yaml</option>
                    <option value="auto">auto</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium">Chunking</label>
                <div class="flex gap-2">
                    <select v-model="form.by" class="border rounded p-2">
                        <option value="size">by size</option>
                        <option value="count">by count</option>
                    </select>

                    <input
                        v-if="form.by==='size'"
                        v-model.number="form.size"
                        type="number"
                        min="1"
                        class="w-full border rounded p-2"
                        placeholder="size"
                    />
                    <input
                        v-else
                        v-model.number="form.count"
                        type="number"
                        min="1"
                        class="w-full border rounded p-2"
                        placeholder="count"
                    />
                </div>
                <p v-if="form.by==='count' && form.count===1" class="text-xs text-amber-600">
                    Using 1 part can exceed QR capacity for large payloads. Consider “by size” or increasing count.
                </p>
            </div>
        </div>

        <!-- Writer controls -->
        <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
            <div class="flex items-center gap-2">
                <input id="incqr" type="checkbox" v-model="include_qr" />
                <label for="incqr" class="text-sm font-medium">Include QR images</label>
            </div>

            <div>
                <label class="block text-sm font-medium">Writer</label>
                <select v-model="writer" class="w-full border rounded p-2" :disabled="!include_qr">
                    <option value="none">none</option>
                    <option value="bacon">bacon</option>
                    <option value="endroid">endroid</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium">Format</label>
                <select
                    v-model="writerFmt"
                    class="w-full border rounded p-2"
                    :disabled="!include_qr || writer==='none'"
                >
                    <option value="svg">svg</option>
                    <option value="png">png</option>
                    <option value="eps" :disabled="writer!=='bacon'">eps</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium">Size</label>
                <input
                    v-model.number="writerSize"
                    type="number"
                    min="64"
                    class="w-full border rounded p-2"
                    :disabled="!include_qr || writer==='none'"
                />
            </div>

            <div>
                <label class="block text-sm font-medium">Margin</label>
                <input
                    v-model.number="writerMargin"
                    type="number"
                    min="0"
                    class="w-full border rounded p-2"
                    :disabled="!include_qr || writer==='none'"
                />
            </div>
            <div>
                <label class="block text-sm font-medium">Template</label>
                <select v-model="form.templateName" class="...">
                    <option disabled value="">Select a template</option>
                    <option v-for="tpl in templates" :key="tpl" :value="tpl">
                        {{ tpl }}
                    </option>
                </select>
            </div>
        </div>

        <!-- Payload -->
        <div>
            <label class="block text-sm font-medium">Payload (JSON)</label>
            <textarea
                class="w-full border rounded p-2 h-40 font-mono text-sm"
                :class="payloadError ? 'border-red-500' : ''"
                :value="rawPayload"
                @input="onPayloadInput"
            ></textarea>
            <p v-if="payloadError" class="text-xs text-red-600 mt-1">{{ payloadError }}</p>
        </div>

        <!-- Actions -->
        <div class="flex gap-3">
            <button @click="onEncode" :disabled="loading || !!payloadError" class="px-4 py-2 rounded bg-black text-white">
                {{ loading ? 'Encoding…' : 'Encode' }}
            </button>
            <button @click="onDecode" :disabled="loading" class="px-4 py-2 rounded bg-gray-200">
                {{ loading ? 'Decoding…' : 'Decode' }}
            </button>

            <button v-if="encodeResult" @click="downloadAll" class="px-4 py-2 rounded bg-gray-100">
                Download lines
            </button>
            <button v-if="encodeResult?.qr" @click="downloadQr" class="px-4 py-2 rounded bg-gray-100">
                Download QR images
            </button>
            <button
                v-if="encodeResult?.qr"
                @click="handleDownload"
                class="px-4 py-2 rounded bg-blue-600 text-white"
            >
                Download PDF
            </button>
        </div>

        <div v-if="error" class="p-3 bg-red-50 text-red-700 border border-red-200 rounded">
            {{ error }}
        </div>

        <!-- Results columns -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Left: Encode + QR gallery -->
            <div class="p-3 bg-gray-50 border rounded overflow-auto">
                <div class="font-semibold mb-2">ENCODE RESULT</div>
                <pre class="text-xs">{{ encodeResult }}</pre>

                <!-- rendered list -->
                <div v-if="encodeResult" class="mt-3 space-y-1">
                    <div class="font-semibold text-sm">Parts</div>
                    <ul class="text-xs space-y-1">
                        <li v-for="(line, idx) in listItems(encodeResult)" :key="idx" class="flex items-center gap-2">
              <span class="inline-flex px-2 py-0.5 rounded bg-gray-200 text-gray-800">
                {{ parseIndexTotal(line)?.i ?? '?' }}/{{ parseIndexTotal(line)?.n ?? '?' }}
              </span>
                            <span class="truncate">{{ line }}</span>
                        </li>
                    </ul>
                </div>

                <!-- QR previews with pagination (SVG + PNG data URLs) -->
                <div v-if="encodeResult?.qr" class="mt-3 space-y-2">
                    <div class="flex items-center justify-between">
                        <div class="font-semibold text-sm">QR Preview</div>
                        <div class="flex items-center gap-2">
                            <button class="px-2 py-1 rounded bg-gray-200 text-xs" :disabled="page <= 1" @click="prevPage">Prev</button>
                            <span class="text-xs text-gray-600">Page {{ page }} / {{ totalPages }}</span>
                            <button class="px-2 py-1 rounded bg-gray-200 text-xs" :disabled="page >= totalPages" @click="nextPage">Next</button>
                            <select v-model.number="perPage" class="text-xs border rounded px-1 py-0.5">
                                <option :value="6">6</option>
                                <option :value="9">9 (3×3)</option>
                                <option :value="12">12</option>
                                <option :value="24">24</option>
                            </select>
                        </div>
                    </div>

                    <!-- 3×3 by default via perPage=9 -->
                    <div class="grid grid-cols-3 gap-3">
                        <div v-for="(val, k) in pagedQrs" :key="k" class="border rounded p-2 bg-white">
                            <!-- SVG string -->
                            <div v-if="typeof val === 'string' && val.trim().startsWith('<?xml')" v-html="val" />
                            <!-- PNG (or other image) data URL -->
                            <img v-else-if="typeof val === 'string' && val.startsWith('data:image')" :src="val" class="w-full h-auto" />
                            <!-- Fallback -->
                            <div v-else class="text-xs text-gray-500">Unsupported QR format</div>
                        </div>
                    </div>

                    <div v-if="qrItems.length > perPage" class="text-xs text-gray-500">
                        Showing {{ (page - 1) * perPage + 1 }}–{{ Math.min(page * perPage, qrItems.length) }} of {{ qrItems.length }}
                    </div>
                </div>
            </div>

            <!-- Right: Decode result + Scanner (NEW) -->
            <div class="p-3 bg-gray-50 border rounded overflow-auto space-y-4">
                <div class="font-semibold mb-2">DECODE RESULT</div>
                <pre class="text-xs">{{ decodeResult }}</pre>

                <div class="space-y-2">
                    <div class="flex items-center gap-2">
                        <button class="px-3 py-1 rounded bg-gray-800 text-white text-sm"
                                @click="startCamera" :disabled="active">Start camera</button>
                        <button class="px-3 py-1 rounded bg-gray-200 text-sm"
                                @click="stopCamera" :disabled="!active">Stop</button>
                        <span class="text-xs text-gray-600">Status: {{ active ? 'Scanning…' : 'Idle' }}</span>
                    </div>

                    <ScannerPanel
                        ref="scannerEl"
                        class="border rounded"
                        :get-decode-args="getDecodeArgs"
                        @started="onStarted"
                        @stopped="onStopped"
                        @detected="onDetected"
                        @decoded="(p: any) => {
                    console.log('[TruthQrForm] @decoded received payload from ScannerPanel:', p);
                    try {
                    // push into JSON editor (updates raw + parsed + clears error)
                    setPayload(p);
                    } catch (e) {
                    console.warn('[TruthQrForm] setPayload failed:', e);
                    }
                    }"
                    />

                    <div class="text-xs text-gray-600">
                        Last scan:
                        <span class="font-mono">{{ lastScan || '—' }}</span>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <button class="px-3 py-1 rounded bg-gray-100 text-sm" @click="addLastScan" :disabled="!lastScan">Add line</button>
                        <button class="px-3 py-1 rounded bg-gray-100 text-sm" @click="clearScanned" :disabled="scannedLines.length===0">Clear</button>
                        <label class="inline-flex items-center gap-2 text-xs">
                            <input type="checkbox" v-model="simulateMissing" />
                            simulate missing (drop middle index)
                        </label>
                        <span v-if="scannedLines.length" class="text-xs text-gray-600">
              Progress: {{ scannedProgress().received }}/{{ scannedProgress().total || '?' }}
            </span>
                    </div>

                    <div v-if="scannedLines.length" class="space-y-1">
                        <div class="font-semibold text-sm">Scanned lines ({{ scannedLines.length }})</div>
                        <ul class="text-xs space-y-1">
                            <li v-for="(ln, i) in scannedLines" :key="i" class="flex items-center gap-2">
                <span class="inline-flex px-2 py-0.5 rounded bg-gray-200 text-gray-800">
                  {{ parseIndexTotal(ln)?.i ?? '?' }}/{{ parseIndexTotal(ln)?.n ?? '?' }}
                </span>
                                <span class="truncate">{{ ln }}</span>
                            </li>
                        </ul>

                        <button class="mt-2 px-3 py-1 rounded bg-black text-white text-sm" @click="decodeScanned">
                            Decode scanned
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
