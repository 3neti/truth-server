import { ref } from 'vue'

export default function usePayloadJson(initial: any) {
    const rawPayload = ref(JSON.stringify(initial, null, 2))
    const payload = ref<any>(initial)
    const payloadError = ref<string | null>(null)

    function onPayloadInput(e: Event) {
        const txt = (e.target as HTMLTextAreaElement).value
        rawPayload.value = txt
        try {
            payload.value = JSON.parse(txt || '{}')
            payloadError.value = null
        } catch {
            payloadError.value = 'Invalid JSON'
        }
    }

    function setPayload(obj: any) {
        payload.value = obj
        rawPayload.value = JSON.stringify(obj, null, 2)
        payloadError.value = null
    }

    return { rawPayload, payload, payloadError, onPayloadInput, setPayload }
}
