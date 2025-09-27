import { ref, onMounted } from 'vue'
import axios from 'axios'

export function useTemplateRegistry() {
    const templates = ref<string[]>([])
    const loading = ref(false)
    const error = ref<Error | null>(null)

    async function fetchTemplates() {
        loading.value = true
        error.value = null
        try {
            const response = await axios.get(route('truth.templates'))
            templates.value = response.data.templates || []
        } catch (err) {
            error.value = err as Error
        } finally {
            loading.value = false
        }
    }

    onMounted(fetchTemplates)

    return {
        templates,
        loading,
        error,
        fetchTemplates,
    }
}
