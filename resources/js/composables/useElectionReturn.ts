import { ref, computed, onBeforeUnmount } from 'vue'
import axios, { type AxiosInstance } from 'axios'
import type { ElectionReturnData } from '@/types/election'

type LoaderMode = 'backend' | 'external'

export interface UseElectionReturnOptions {
    /** If provided, use this absolute/relative URL and ignore Ziggy. */
    endpoint?: string | null
    /** Ziggy route name to call (default: 'election-return'). */
    routeName?: string
    /** Ziggy route params, if any. */
    routeParams?: Record<string, any>
    /** Auto-fetch immediately (default: false). */
    immediate?: boolean
    /** Optional custom axios instance. */
    axios?: AxiosInstance
    /** Optional polling interval in ms (disabled if not set or < 1000). */
    pollMs?: number
}

function resolveEndpoint(opts: UseElectionReturnOptions): string {
    if (opts.endpoint) return opts.endpoint
    const name = opts.routeName ?? 'election-return'
    // Prefer global Ziggy route() if present
    if (typeof (window as any).route === 'function') {
        try { return (window as any).route(name, opts.routeParams ?? {}) } catch { /* fall through */ }
    }
    return '/api/election-return'
}

function validateErShape(obj: any): asserts obj is ElectionReturnData {
    if (!obj || !obj.precinct || !obj.precinct.code || !Array.isArray(obj.tallies)) {
        throw new Error('JSON does not look like an Election Return payload.')
    }
}

export function useElectionReturn(options: UseElectionReturnOptions = {}) {
    const $axios = options.axios ?? axios

    const er = ref<ElectionReturnData | null>(null)
    const loading = ref(false)
    const error = ref<string | null>(null)
    const lastUpdated = ref<number | null>(null)
    const sourceMode = ref<LoaderMode>('external') // switches to 'backend' after a successful fetch

    // ---- NEW: AbortController instead of CancelToken
    let controller: AbortController | null = null
    let pollTimer: number | null = null

    async function loadFromBackend(): Promise<void> {
        const url = resolveEndpoint(options)

        // cancel any in-flight request
        if (controller) controller.abort()
        controller = new AbortController()

        loading.value = true
        error.value = null
        try {
            const { data } = await $axios.get<ElectionReturnData>(url, {
                signal: controller.signal,
            })
            validateErShape(data)
            er.value = data
            lastUpdated.value = Date.now()
            sourceMode.value = 'backend'
        } catch (e: any) {
            // Only bail out silently if it was a cancel
            if (e?.code === 'ERR_CANCELED') return
            error.value = e?.response?.data?.message || e?.message || String(e)
        } finally {
            loading.value = false
        }
    }

    function setFromJson(json: unknown): void {
        console.log('[useElectionReturn] setFromJson input:', json)
        try {
            const obj = typeof json === 'string' ? JSON.parse(json) : json
            validateErShape(obj)
            er.value = obj
            lastUpdated.value = Date.now()
            sourceMode.value = 'external'
            error.value = null
        } catch (e: any) {
            error.value = e?.message || String(e)
            throw e
        }
    }

    function reset(): void {
        er.value = null
        error.value = null
        lastUpdated.value = null
    }

    function startPolling(): void {
        const ms = options.pollMs ?? 0
        if (!ms || ms < 1000) return
        stopPolling()
        pollTimer = window.setInterval(() => {
            // Only poll when backend is the current source
            if (sourceMode.value === 'backend') {
                loadFromBackend().catch(() => {})
            }
        }, ms) as unknown as number
    }

    function stopPolling(): void {
        if (pollTimer != null) {
            clearInterval(pollTimer)
            pollTimer = null
        }
    }

    onBeforeUnmount(() => {
        stopPolling()
        if (controller) controller.abort()
    })

    if (options.immediate) {
        // Fire and forget
        loadFromBackend().catch(() => {})
    }
    if (options.pollMs && options.pollMs >= 1000) {
        startPolling()
    }

    const ready = computed(() => !!er.value)

    return {
        // state
        er,
        loading,
        error,
        lastUpdated,
        ready,
        sourceMode,

        // actions
        loadFromBackend,
        setFromJson,
        reset,
        startPolling,
        stopPolling,
    }
}
