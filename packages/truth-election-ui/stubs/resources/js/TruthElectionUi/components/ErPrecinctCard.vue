<script setup lang="ts">
import { computed } from 'vue'
import { mapsHref } from '@/composables/useBasicUtils'
import type { ElectionReturnData } from '@/types/election'

const props = defineProps<{
    er: ElectionReturnData
    title?: string
}>()

const hasPrecinctExtras = computed(() => {
    const p = props.er?.precinct
    return !!(p?.location_name || p?.latitude != null || p?.longitude != null)
})
</script>

<template>
    <!-- Parent-provided class merges automatically here -->
    <div v-if="hasPrecinctExtras" class="p-4 border rounded bg-gray-50">
        <h3 class="text-sm font-semibold mb-2">{{ title ?? 'Precinct' }}</h3>
        <dl class="text-sm space-y-1">
            <div v-if="er.precinct.location_name" class="flex gap-2">
                <dt class="text-gray-600 w-28">Location</dt>
                <dd class="font-medium">{{ er.precinct.location_name }}</dd>
            </div>
            <div v-if="er.precinct.latitude != null || er.precinct.longitude != null" class="flex gap-2">
                <dt class="text-gray-600 w-28">Coordinates</dt>
                <dd class="font-mono">
                    <template v-if="er.precinct.latitude != null">{{ er.precinct.latitude }}</template>
                    <template v-if="er.precinct.latitude != null && er.precinct.longitude != null">, </template>
                    <template v-if="er.precinct.longitude != null">{{ er.precinct.longitude }}</template>
                    <a
                        v-if="mapsHref(er.precinct.latitude, er.precinct.longitude)"
                        :href="mapsHref(er.precinct.latitude, er.precinct.longitude)!"
                        target="_blank"
                        rel="noopener"
                        class="ml-2 underline text-blue-700"
                        title="Open in Google Maps"
                    >
                        Map
                    </a>
                </dd>
            </div>
        </dl>
    </div>
</template>
