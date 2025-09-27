// Tiny, pure helpers used across print/QR views.

export function formatWhen(s?: string | null): string {
    if (!s) return ''
    try { return new Date(s).toLocaleString() } catch { return String(s ?? '') }
}

export function mapsHref(lat?: number | null, lon?: number | null): string | null {
    if (lat == null || lon == null) return null
    return `https://maps.google.com/?q=${lat},${lon}`
}

export function copyTextChunk(txt?: string): void {
    if (!txt) return
    try {
        // Fire-and-forget; don't surface clipboard errors to the UI here.
        void navigator.clipboard?.writeText(txt)
    } catch {
        // noop
    }
}
