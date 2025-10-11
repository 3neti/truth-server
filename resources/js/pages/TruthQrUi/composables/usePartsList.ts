export default function usePartsList() {
    function listItems(enc: any): string[] {
        if (!enc) return []
        if (Array.isArray(enc.urls)) return enc.urls as string[]
        if (Array.isArray(enc.lines)) return enc.lines as string[]
        return []
    }
    function hasParts(enc: any): boolean {
        return listItems(enc).length > 0
    }
    return { listItems, hasParts }
}
