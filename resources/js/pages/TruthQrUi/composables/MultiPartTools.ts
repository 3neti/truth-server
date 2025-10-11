export function parseIndexTotal(line: string): { i: number; n: number } | null {
    // URL deep link: <scheme>://<version>/<prefix>/<code>/<i>/<n>?...
    const urlLike = line.match(/^[a-z]+:\/\/([^/]+)\/([^/]+)\/([^/]+)\/(\d+)\/(\d+)(\?|$)/i)
    if (urlLike) {
        const i = Number(urlLike[4])
        const n = Number(urlLike[5])
        return i && n ? { i, n } : null
    }

    // Line: <PREFIX>|<version>|<CODE>|<i>/<n>|<payload>
    const parts = line.split('|')
    if (parts.length >= 4) {
        const idx = parts[3]
        const m = idx.match(/^(\d+)\/(\d+)$/)
        if (m) {
            const i = Number(m[1])
            const n = Number(m[2])
            return i && n ? { i, n } : null
        }
    }

    return null
}
