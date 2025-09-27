import { downloadText, downloadDataUrl } from './download'

export default function useDownloads() {
    function downloadAllLines(code: string | undefined, items: string[]) {
        if (!items.length) return
        const content = items.join('\n')
        downloadText(`${code ?? 'truth'}-chunks.txt`, content)
    }

    function downloadQrAssets(code: string | undefined, qr: Record<string, string> | undefined) {
        if (!qr) return
        const items = Object.values(qr) as string[]
        items.forEach((data, idx) => {
            const base = `${code ?? 'truth'}-qr-${idx + 1}`
            if (typeof data !== 'string') return
            const s = data.trim()
            if (s.startsWith('<?xml')) downloadText(`${base}.svg`, data)
            else if (s.startsWith('data:image/png')) downloadDataUrl(`${base}.png`, data)
        })
    }

    return { downloadAllLines, downloadQrAssets }
}
