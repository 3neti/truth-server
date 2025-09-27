<script setup lang="ts">

import ErOfficialsSignatures from '@/components/ErOfficialsSignatures.vue'
import { computed, ref, watch, onBeforeUnmount, onMounted } from 'vue'
import ErPrecinctCard from '@/components/ErPrecinctCard.vue'
import ErTalliesTable from '@/components/ErTalliesTable.vue'
import type { ElectionReturnData } from '@/types/election'
import { useEchoPublic } from '@laravel/echo-vue'

/** ---------------- Props ---------------- */
const props = defineProps<{
    er: ElectionReturnData
    precinctCode?: string
    /** heading override */
    title?: string
    /** ms to keep highlight flash for last_ballot changes */
    flashMs?: number
}>()

const emit = defineEmits<{
    (e: 'refresh-request'): void
}>()

/** ---------------- Local state ---------------- */
const highlights = ref<Set<string>>(new Set())
const flashing = ref<Set<string>>(new Set())

/** ---------------- Helpers ---------------- */
function computeHighlights(er: ElectionReturnData | null): Set<string> {
    const set = new Set<string>()
    if (!er?.last_ballot?.votes) return set

    for (const v of er.last_ballot.votes as any[]) {
        const pos =
            v.position_code ??
            v.position?.code ??
            (typeof v.position === 'object' ? v.position?.code : undefined)

        const cands: any[] = Array.isArray(v.candidate_codes)
            ? v.candidate_codes
            : Array.isArray(v.candidates)
                ? v.candidates
                : []

        for (const c of cands) {
            const code = typeof c === 'string' ? c : c?.code
            if (pos && code) set.add(`${pos}::${code}`)
        }
    }
    return set
}

function triggerFlash(newSet: Set<string>, ms = 1200) {
    if (!newSet.size) return
    flashing.value = new Set(newSet)
    setTimeout(() => { flashing.value = new Set() }, ms)
}

/** ---------------- Derived ---------------- */
const hasPrecinctExtras = computed(() => {
    const p = props.er?.precinct
    return !!(p?.location_name || p?.latitude || p?.longitude)
})

const hasPeople = computed(() => {
    const inspectors = props.er?.precinct?.electoral_inspectors ?? []
    const sigs       = props.er?.signatures ?? []
    return inspectors.length > 0 || sigs.length > 0
})

/** ---------------- Lifecycle / watchers ---------------- */
onMounted(() => {
    const set = computeHighlights(props.er)
    highlights.value = set
    triggerFlash(set, props.flashMs ?? 1200)
})

// --- Realtime (public) – emit refresh requests up to the parent
interface BallotSubmittedEvent {
    ballot?: { precinct?: { code?: string } }
}

let refreshTimer: number | null = null
function requestRefreshSoon(ms = 250) {
    if (refreshTimer) window.clearTimeout(refreshTimer)
    refreshTimer = window.setTimeout(() => {
        emit('refresh-request')
        refreshTimer = null
    }, ms) as unknown as number
}

const channelName = computed(
    () => `precinct.${props.precinctCode ?? 'DEFAULT-PRECINCT'}`
)

const { listen, stopListening, leaveChannel } = useEchoPublic<BallotSubmittedEvent>(
    channelName.value,
    '.ballot.submitted',
    (payload) => {
        try {
            const pcode = payload?.ballot?.precinct?.code
            if (pcode && props.precinctCode && pcode !== props.precinctCode) {
                // ignore other precincts (defensive)
                return
            }
            requestRefreshSoon(250)
        } catch (e) {
            console.error('[ErTallyView] listener error', e)
        }
    }
)

onMounted(() => {
    // existing highlight bootstrapping
    const set = computeHighlights(props.er)
    highlights.value = set
    triggerFlash(set, props.flashMs ?? 1200)

    listen()
})

onBeforeUnmount(() => {
    stopListening()
    leaveChannel(true)
})

watch(() => props.er?.last_ballot, () => {
    const set = computeHighlights(props.er)
    highlights.value = set
    triggerFlash(set, props.flashMs ?? 1200)
})
</script>

<template>
    <div class="space-y-6">
        <header class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold">
                    {{ title ?? 'Tally for Precinct ' + (er?.precinct.code ?? '—') }}
                </h1>
                <p class="text-sm text-gray-600">
                    ER Code: <span class="font-mono">{{ er?.code }}</span>
                </p>
            </div>
        </header>

        <!-- Precinct + Officials (merged with signatures) -->
        <section class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Precinct card -->
            <div v-if="hasPrecinctExtras" class="p-4 border rounded bg-gray-50">
                <ErPrecinctCard :er="er" class="mb-3" />
            </div>

            <!-- Combined Officials & Signatures card (no “Source” column) -->
            <div v-if="hasPeople" class="rounded bg-gray-50 md:col-span-2">
                <ErOfficialsSignatures
                    :er="er"
                    :hide-when-empty="true"
                />
            </div>
        </section>

        <!-- Tallies Table -->
        <ErTalliesTable
            :tallies="er?.tallies ?? []"
            :highlight-keys="highlights"
            :flash-keys="flashing"
            highlight-color="red"
        />
    </div>
</template>
