<script setup lang="ts">
import { ref, computed } from 'vue'
import useScannerSession, { type GetDecodeArgs } from '../composables/useScannerSession'
import useZxingVideo from '../composables/useZxingVideo'
import { parseIndexTotal } from '../composables/MultiPartTools'

const props = defineProps<{ getDecodeArgs: GetDecodeArgs }>()
const emit = defineEmits<{
    (e: 'decoded', payload: any): void
    (e: 'detected', text: string): void
    (e: 'started'): void
    (e: 'stopped'): void
    (e: 'progress', status: {
        code: string
        total: number
        received: number
        missing: number[]
        complete: boolean
    }): void
}>()

console.log('[ScannerPanel] mounted with props.getDecodeArgs:', typeof props.getDecodeArgs)

// Shared buffer + backend decode
const paste = ref('')
const sess = useScannerSession(props.getDecodeArgs)

// ZXing video scanner (unchanged except no auto-decode)
const { videoEl, active: camActive, start: startVideo, stop: stopVideo } = useZxingVideo({
    onDetected: (raw) => {
        const text = (typeof raw === 'string' ? raw : raw?.getText?.())?.trim() ?? ''
        if (!text) return
        const looksTruthy = /^truth:\/\//i.test(text) || /[?&]c=/.test(text) || !!parseIndexTotal(text)
        if (!looksTruthy) {
            console.log('[ScannerPanel] onDetected ignored (not a TRUTH chunk):', text)
            return
        }
        console.log('[ScannerPanel] onDetected (ZXING):', text)
        emit('detected', text)
        const before = sess.lines.value.length
        sess.addScan(text)
        const after = sess.lines.value.length
        console.log('[ScannerPanel] onDetected →', after > before ? 'accepted' : 'duplicate', 'lines:', after)
    },
    onStarted: (deviceId) => { console.log('[ScannerPanel] onStarted (ZXING) deviceId:', deviceId); emit('started') },
    onStopped: () => { console.log('[ScannerPanel] onStopped (ZXING)'); emit('stopped') },
})

// Parent controls
function start() { console.log('[ScannerPanel] start() called by parent'); startVideo() }
function stop()  { console.log('[ScannerPanel] stop() called by parent');  stopVideo()  }
defineExpose({ start, stop })

// Sort lines by i when possible
const sortedLines = computed(() => {
    return [...sess.lines.value].sort((a, b) => {
        const pa = parseIndexTotal(a); const pb = parseIndexTotal(b)
        if (pa && pb) return pa.i - pb.i
        return 0
    })
})

/** Inferred completeness from i/n without backend */
const inferredStatus = computed(() => {
    // collect unique indices and the largest n we see
    const seen = new Set<number>()
    let maxN = 0
    for (const ln of sess.lines.value) {
        const meta = parseIndexTotal(ln)
        if (meta) {
            if (meta.i > 0) seen.add(meta.i)
            if (meta.n > maxN) maxN = meta.n
        }
    }
    const received = seen.size
    const total = maxN || (received > 0 ? received : 0) // if no n found, fall back so UI doesn't show 0/?
    const complete = total > 0 && received >= total
    // helpful logs
    console.log('[ScannerPanel] inferredStatus →', { received, total, complete })
    return { received, total, complete }
})

async function onDecode() {
    console.log('[ScannerPanel] onDecode() click; lines:', sess.lines.value.length)
    try {
        const res = await sess.decodeNow()
        console.log('[ScannerPanel] decodeNow() result:', res)
        emit('progress', {
            code: String(res?.code ?? ''),
            total: Number(res?.total ?? 0),
            received: Number(res?.received ?? 0),
            missing: Array.isArray(res?.missing) ? res?.missing : [],
            complete: Boolean(res?.complete),
        })
        if (res?.complete && res?.payload) {
            console.log('[ScannerPanel] emit decoded payload')
            emit('decoded', res.payload)
        }
    } catch (e) {
        console.error('[ScannerPanel] onDecode error:', e)
    }
}

function short(line: string) {
    if (line.length <= 64) return line
    return `${line.slice(0, 28)}…${line.slice(-28)}`
}
</script>

<template>
    <div class="border rounded p-3 space-y-3">
        <div class="flex items-center justify-between">
            <div class="font-semibold">Scanner</div>
            <div class="text-xs text-gray-500">
                Status: {{ sess.loading ? 'Decoding…' : (camActive ? 'Camera On' : 'Idle') }}
            </div>
        </div>

        <!-- Live camera preview -->
        <div class="relative rounded overflow-hidden border">
            <video ref="videoEl" class="w-full h-auto block bg-black" autoplay playsinline muted />
        </div>

        <!-- Controls for camera -->
        <div class="flex items-center gap-2">
            <button type="button" class="px-3 py-2 rounded bg-gray-800 text-white" @click="start" :disabled="!!camActive">
                Start camera
            </button>
            <button type="button" class="px-3 py-2 rounded bg-gray-200" @click="stop" :disabled="!camActive">
                Stop
            </button>
        </div>

        <!-- Manual paste capture -->
        <div class="grid gap-2 md:grid-cols-2">
      <textarea
          v-model="paste"
          class="w-full border rounded p-2 h-24 font-mono text-xs"
          placeholder="Paste one or more TRUTH lines or URLs (newline-separated)…"
      />
            <div class="flex items-start gap-2">
                <button
                    type="button"
                    class="px-3 py-2 rounded bg-gray-800 text-white"
                    @click="async () => {
            console.log('[ScannerPanel] Add clicked');
            sess.addMany(paste);
            paste = '';
            await onDecode(); // unchanged: manual paste auto-decodes
          }"
                >
                    Add
                </button>

                <button type="button" class="px-3 py-2 rounded bg-gray-200" @click="() => { console.log('[ScannerPanel] Reset clicked'); sess.clear() }">
                    Reset
                </button>

                <button type="button" class="px-3 py-2 rounded bg-gray-200" @click="() => { console.log('[ScannerPanel] Simulate Missing clicked'); sess.simulateMissing() }">
                    Simulate Missing
                </button>

                <button
                    type="button"
                    class="px-3 py-2 rounded bg-black text-white ml-auto"
                    :title="inferredStatus.complete ? 'Ready to decode' : `Need ${Math.max(inferredStatus.total - inferredStatus.received, 1)} more chunk(s)`"
                    :disabled="Boolean(!inferredStatus.complete || sess.loading)"
                    @click="onDecode"
                >
                    Decode
                </button>
            </div>
        </div>

        <div v-if="sess.error" class="p-2 text-sm bg-red-50 border border-red-200 text-red-700 rounded">
            {{ sess.error }}
        </div>

        <!-- Progress HUD (inferred, pre-decode) -->
        <div class="text-xs text-gray-700">
            <div class="flex flex-wrap items-center gap-3">
                <span>Inferred: {{ inferredStatus.received }}/{{ inferredStatus.total || '?' }}</span>
                <span>Complete: <strong>{{ inferredStatus.complete ? 'Yes' : 'No' }}</strong></span>
            </div>
        </div>

        <!-- Backend decode status (post-decode) -->
        <div class="text-xs text-gray-700">
            <div class="flex flex-wrap items-center gap-3">
                <span>Code: <span class="font-mono">{{ sess.status.value.code || '—' }}</span></span>
                <span>Total: {{ sess.status.value.total }}</span>
                <span>Received: {{ sess.status.value.received }}</span>
                <span>
          Missing:
          <span v-if="sess.status.value.missing.length" class="font-mono">{{ sess.status.value.missing.join(', ') }}</span>
          <span v-else>—</span>
        </span>
                <span>Complete: <strong>{{ sess.status.value.complete ? 'Yes' : 'No' }}</strong></span>
            </div>
        </div>

        <!-- Collected lines -->
        <div v-if="sortedLines.length" class="space-y-2">
            <div class="text-sm font-semibold">Captured Chunks ({{ sortedLines.length }})</div>
            <ul class="space-y-1">
                <li v-for="line in sortedLines" :key="line" class="flex items-center gap-2 text-xs">
          <span class="inline-flex px-2 py-0.5 rounded bg-gray-200 text-gray-800">
            {{ parseIndexTotal(line)?.i ?? '?' }}/{{ parseIndexTotal(line)?.n ?? '?' }}
          </span>
                    <span class="font-mono truncate flex-1">{{ short(line) }}</span>
                    <button type="button" class="px-2 py-0.5 rounded bg-gray-100" @click="() => { console.log('[ScannerPanel] Remove clicked', line); sess.remove(line) }">
                        Remove
                    </button>
                </li>
            </ul>
        </div>
    </div>
</template>
