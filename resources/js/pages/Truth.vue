<script setup lang="ts">
import { ref } from 'vue'
import ErTallyView from '@/components/ErTallyView.vue'
import ScannerPanel from '@/pages/TruthQrUi/components/ScannerPanel.vue'
import { useElectionReturn } from '@/composables/useElectionReturn'

/** Election Return backend composable */
const {
    er,
    error: erError,
    loading: erLoading,
    setFromJson,
    reset: resetEr,
} = useElectionReturn({
    routeName: 'election-return',
    immediate: false,
})

/** Chunk progress + session stats */
const scannerActive = ref(true)
const chunkCode = ref<string | null>(null)
const collectedLines = ref(0)
const missingCount = ref(0)

/** Reset flow */
function hardReset() {
    resetEr()
    chunkCode.value = null
    collectedLines.value = 0
    missingCount.value = 0
    scannerActive.value = true
}

/** When ScannerPanel emits a decoded payload */
function onDecoded(payload: any) {
    console.log('[Truth.vue] Received decoded ER payload')
    try {
        setFromJson(payload)
        scannerActive.value = false
    } catch (e) {
        console.warn('[Truth.vue] Failed to parse decoded payload:', e)
    }
}

/** When ScannerPanel reports progress */
function onProgress({
                        code,
                        received,
                        missing,
                    }: {
    code: string
    total: number
    received: number
    missing: number[]
    complete: boolean
}) {
    chunkCode.value = code
    collectedLines.value = received
    missingCount.value = missing.length
}

</script>

<template>
    <div class="max-w-5xl mx-auto p-6 space-y-6">
        <!-- HEADER -->
        <header class="flex items-center justify-between gap-4">
            <h1 class="text-xl font-semibold">TRUTH — Reconstruct Election Return</h1>
            <div class="flex items-center gap-2 text-xs">
        <span class="px-2 py-1 rounded bg-slate-100 border">
          Collected {{ collectedLines }} chunk{{ collectedLines === 1 ? '' : 's' }}
        </span>
                <span v-if="chunkCode" class="px-2 py-1 rounded bg-slate-100 border">
          Code: {{ chunkCode }}
        </span>
                <span class="px-2 py-1 rounded bg-slate-100 border">
          ER ready: {{ er ? 'yes' : 'no' }}
        </span>
            </div>
        </header>

        <!-- RENDERED ELECTION RETURN -->
        <section v-if="er" class="border rounded p-4">
            <ErTallyView :er="er" title="Election Return" />
        </section>
        <p v-else class="text-sm text-gray-600">
            Scan all QR chunks of the ER using your device camera. Once the data is complete, the full Election Return will automatically render.
        </p>

        <!-- SCANNER PANEL -->
        <section class="rounded border p-4 space-y-4">
            <ScannerPanel
                v-if="scannerActive"
                :get-decode-args="() => ({
          envelope: 'v1line',
          prefix: 'TRUTH',
          version: 'v1',
          transport: 'base64url+deflate',
          serializer: 'json',
        })"
                @decoded="onDecoded"
                @progress="onProgress"
                @started="() => console.log('[Truth.vue] Scanner started')"
                @stopped="() => console.log('[Truth.vue] Scanner stopped')"
            />

            <!-- Reset & Error -->
            <div class="flex items-center gap-2">
                <button
                    class="px-3 py-2 rounded bg-slate-700 text-white"
                    :disabled="erLoading"
                    @click="hardReset"
                >
                    {{ erLoading ? 'Resetting…' : 'Reset All' }}
                </button>
            </div>

            <p v-if="erError" class="text-sm text-red-600">
                Error loading Election Return: {{ erError }}
            </p>
            <!-- DEV DEBUG: Show raw decoded payload -->
            <pre v-if="er" class="text-xs bg-gray-50 p-2 rounded border overflow-auto max-h-64">
{{ JSON.stringify(er, null, 2) }}
</pre>
        </section>

    </div>
</template>
