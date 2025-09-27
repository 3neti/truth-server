import { computed, type Ref } from 'vue'

export type MergedSigner = {
    key: string
    name: string
    role?: string | null
    signed_at?: string | null
}

// Keep the same public name you referenced earlier
export function usePrecinctPeople(erRef: Ref<{ precinct?: {
        electoral_inspectors?: Array<{ id: string; name: string; role?: string | null }>
    }; signatures?: Array<{ id?: string; name?: string; role?: string | null; signed_at?: string | null }> } | null | undefined>) {

    const mergedPeople = computed<MergedSigner[]>(() => {
        const map = new Map<string, MergedSigner>()

        const insp = erRef.value?.precinct?.electoral_inspectors ?? []
        for (const i of insp) {
            const key = `${(i.name || '').trim().toLowerCase()}|${(i.role || '').trim().toLowerCase()}`
            map.set(key, { key, name: i.name, role: i.role ?? null, signed_at: undefined })
        }

        const sigs = erRef.value?.signatures ?? []
        for (const s of sigs) {
            const key = `${(s.name || '').trim().toLowerCase()}|${(s.role || '').trim().toLowerCase()}`
            const prev = map.get(key)
            if (prev) {
                // preserve earliest known signed_at if multiple
                prev.signed_at = prev.signed_at ?? s.signed_at
            } else {
                map.set(key, { key, name: s.name || '—', role: s.role ?? null, signed_at: s.signed_at })
            }
        }

        return Array.from(map.values()).sort((a, b) => {
            const ra = (a.role || '').toLowerCase()
            const rb = (b.role || '').toLowerCase()
            if (ra !== rb) return ra < rb ? -1 : 1
            const na = a.name.toLowerCase()
            const nb = b.name.toLowerCase()
            return na < nb ? -1 : na > nb ? 1 : 0
        })
    })

    const hasPeople = computed(() => mergedPeople.value.length > 0)

    return { mergedPeople, hasPeople }
}
