<script setup lang="ts">
import { toRef, computed } from 'vue'
import { usePrecinctPeople } from '@/composables/usePrecinctPeople'
import { formatWhen } from '@/composables/useBasicUtils'
import type { ElectionReturnData } from '@/types/election'

/** ---------------- Props ---------------- */
const props = withDefaults(defineProps<{
    er: ElectionReturnData
    /** Card title */
    title?: string
    /** Hide the whole block when no people */
    hideWhenEmpty?: boolean
    /** Show the two blank signature boxes (used by ElectionReturn) */
    showSignatureLines?: boolean
    /** Extra classes for the outer wrapper (useful for grid spans, etc.) */
    class?: string
}>(), {
    title: 'Officials & Signatures',
    hideWhenEmpty: true,
    showSignatureLines: false,
})

/** ---------------- Data (from composable) ---------------- */
const { mergedPeople, hasPeople } = usePrecinctPeople(toRef(props, 'er'))

/** ---------------- Guards ---------------- */
const shouldRender = computed(() => hasPeople.value || !props.hideWhenEmpty)
</script>

<template>
    <div v-if="shouldRender" :class="['p-4 border rounded bg-gray-50', props.class]">
        <!-- Header (slot-friendly) -->
        <h3 class="text-sm font-semibold mb-2">
            <slot name="header">{{ title }}</slot>
        </h3>

        <!-- Table (copied style from ErTallyView.vue) -->
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                <tr class="text-left text-gray-600 uppercase text-xs">
                    <th class="py-2 pr-3">Name</th>
                    <th class="py-2 pr-3">Role</th>
                    <th class="py-2">Status</th>
                </tr>
                </thead>
                <tbody>
                <tr v-for="p in mergedPeople" :key="p.key" class="border-t">
                    <td class="py-2 pr-3 font-medium">
                        {{ p.name }}
                    </td>
                    <td class="py-2 pr-3 text-xs uppercase tracking-wide text-gray-700">
                        {{ p.role || '—' }}
                    </td>
                    <td class="py-2">
                        <!-- status slot override (optional) -->
                        <slot name="status" :signed_at="p.signed_at">
                <span
                    v-if="p.signed_at"
                    class="inline-block px-2 py-0.5 rounded bg-emerald-100 text-emerald-800"
                >
                  signed: {{ formatWhen(p.signed_at) }}
                </span>
                            <span
                                v-else
                                class="inline-block px-2 py-0.5 rounded bg-amber-100 text-amber-800"
                            >
                  pending
                </span>
                        </slot>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>

        <!-- Optional signature lines (for printing) -->
        <div v-if="showSignatureLines" class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4 print:mt-6">
            <div class="border rounded p-3 text-center">
                <div class="h-12"></div>
                <div class="border-t pt-1 mt-2 text-xs text-gray-600">
                    Signature over Printed Name
                </div>
            </div>
            <div class="border rounded p-3 text-center">
                <div class="h-12"></div>
                <div class="border-t pt-1 mt-2 text-xs text-gray-600">
                    Signature over Printed Name
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* none needed; inherits Tailwind/utility classes from parent app */
</style>
