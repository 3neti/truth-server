<script setup lang="ts">
import { computed } from 'vue'
import { TallyMarks } from '@lbhurtado/vue-tally-marks'
import type { TallyData } from '@/types/election'

/** Props */
const props = withDefaults(defineProps<{
    tallies: TallyData[]
    /** Keys to highlight (e.g., new votes). Accepts Set<string> or string[] */
    highlightKeys?: Set<string> | string[]
    /** Keys to “flash ring” once (transient UX). Accepts Set<string> or string[] */
    flashKeys?: Set<string> | string[]
    /** Color name forwarded to TallyMarks when highlighted */
    highlightColor?: string
    /** Optional: override column labels */
    colLabels?: {
        position?: string
        candidate?: string
        votes?: string
        tally?: string
    }
}>(), {
    tallies: () => [],
    highlightColor: 'red',
    colLabels: () => ({
        position: 'Position',
        candidate: 'Candidate',
        votes: 'Votes',
        tally: 'Tally'
    })
})

/** Normalize Set/Array → Set for O(1) membership checks */
function toSet(v?: Set<string> | string[]): Set<string> {
    if (!v) return new Set()
    return v instanceof Set ? v : new Set(v)
}

const highlightSet = computed(() => toSet(props.highlightKeys))
const flashSet = computed(() => toSet(props.flashKeys))

/** Stable key format shared across app */
function keyOf(t: TallyData): string {
    return `${t.position_code}::${t.candidate_code}`
}
</script>

<template>
    <section>
        <table class="table-auto w-full border text-sm">
            <thead class="bg-gray-200 text-left uppercase text-xs">
            <tr>
                <th class="px-3 py-2">{{ colLabels.position }}</th>
                <th class="px-3 py-2">{{ colLabels.candidate }}</th>
                <th class="px-3 py-2 text-center">{{ colLabels.votes }}</th>
                <th class="px-3 py-2">{{ colLabels.tally }}</th>
            </tr>
            </thead>

            <tbody>
            <tr
                v-for="(tally, index) in tallies"
                :key="index"
                class="border-t transition-colors duration-700 relative"
                :class="{
            'bg-red-50': highlightSet.has(keyOf(tally)),
            'flash-ring': flashSet.has(keyOf(tally))
          }"
            >
                <td class="px-3 py-2 font-mono">{{ tally.position_code }}</td>

                <td
                    class="px-3 py-2 transition-colors duration-700"
                    :class="{ 'text-red-600 font-bold': highlightSet.has(keyOf(tally)) }"
                >
                    {{ tally.candidate_name }}
                </td>

                <td class="px-3 py-2 text-center font-semibold">{{ tally.count }}</td>

                <td class="px-3 py-2">
                    <TallyMarks
                        :count="tally.count"
                        :highlight-color="highlightSet.has(keyOf(tally)) ? highlightColor : undefined"
                    />
                </td>
            </tr>
            </tbody>
        </table>
    </section>
</template>

<style scoped>
@keyframes ringPulse {
    0%   { box-shadow: 0 0 0 8px rgba(239, 68, 68, 0.25); }
    100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
}
.flash-ring { animation: ringPulse 0.9s ease-out 1; }
</style>
