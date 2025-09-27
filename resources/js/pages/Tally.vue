<script setup lang="ts">
import { useElectionReturn } from '@/composables/useElectionReturn'
import ErTallyView from '@/components/ErTallyView.vue'
import type { PrecinctData } from '@/types/election'
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

// Election Return state (loads immediately from backend)
const { er, loading: erLoading, error: erError, loadFromBackend } = useElectionReturn({
    routeName: 'election-return', // make sure your Ziggy route name matches
    immediate: true,
})

const precinct = usePage().props.precinct as PrecinctData
const noDataYet = computed(() => erError?.includes('Election Return'))

</script>

<template>
    <div class="max-w-5xl mx-auto p-6 space-y-6">
        <header class="flex items-center justify-between">
            <h1 class="text-xl font-semibold"></h1>
            <button class="px-3 py-1 rounded bg-slate-700 text-white" :disabled="erLoading" @click="loadFromBackend">
                {{ erLoading ? 'Loading…' : 'Refresh' }}
            </button>
        </header>

<!--        <p v-if="erError" class="text-sm text-red-600">Error: {{ erError }}</p>-->
        <p v-if="erError && erError.includes('Election Return')" class="text-sm text-gray-600">
            No election return data yet.
        </p>
        <p v-else-if="erError" class="text-sm text-red-600">
            Error: {{ erError }}
        </p>
<!--        <p v-if="noDataYet" class="text-sm text-gray-600">-->
<!--            No election return data yet. Try refreshing.-->
<!--        </p>-->
<!--        <p v-else-if="erError" class="text-sm text-red-600">-->
<!--            Error: {{ erError }}-->
<!--        </p>-->

        <section v-if="er" class="border rounded p-4">
            <ErTallyView
                :er="er"
                :precinctCode="precinct.code"
                @refresh-request="loadFromBackend"
            />
        </section>
        <p v-else class="text-sm text-gray-600">Click “Refresh” to try loading the election return.</p>
    </div>
</template>
