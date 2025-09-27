import { ref, computed, watch, Ref } from 'vue'

export default function useQrGallery(encodeResult: Ref<any>, defaultPerPage = 9) {
    const perPage = ref(defaultPerPage) // 9 → 3×3
    const page = ref(1)

    const qrItems = computed<string[]>(() => {
        const raw = encodeResult.value?.qr
        if (!raw || typeof raw !== 'object') return []
        return Object.values(raw) as string[]
    })

    const totalPages = computed(() => {
        return qrItems.value.length ? Math.ceil(qrItems.value.length / perPage.value) : 1
    })

    const pagedQrs = computed(() => {
        const start = (page.value - 1) * perPage.value
        return qrItems.value.slice(start, start + perPage.value)
    })

    function prevPage() { if (page.value > 1) page.value-- }
    function nextPage() { if (page.value < totalPages.value) page.value++ }
    function resetOn(resultRef?: Ref<any>) {
        watch(() => resultRef ? resultRef.value?.qr : encodeResult.value?.qr, () => { page.value = 1 })
    }

    // default reset on the provided encodeResult
    resetOn()

    return { perPage, page, qrItems, totalPages, pagedQrs, prevPage, nextPage, resetOn }
}
