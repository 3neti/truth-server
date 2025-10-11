import { computed, ref } from 'vue'

export type WriterKind = 'none' | 'bacon' | 'endroid'
export type WriterFmt = 'svg' | 'png' | 'eps'

export default function useWriterSpec() {
    const include_qr = ref(false)
    const writer = ref<WriterKind>('bacon')
    const writerFmt = ref<WriterFmt>('svg')
    const writerSize = ref(256)
    const writerMargin = ref(16)

    const isWriterEnabled = computed(() => include_qr.value && writer.value !== 'none')

    function buildWriterSpec(): string {
        if (!isWriterEnabled.value) return ''
        return `${writer.value}(${writerFmt.value},size=${writerSize.value},margin=${writerMargin.value})`
    }

    return {
        include_qr, writer, writerFmt, writerSize, writerMargin,
        isWriterEnabled, buildWriterSpec,
    }
}
