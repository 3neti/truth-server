export function downloadText(name: string, content: string) {
    const blob = new Blob([content], { type: 'text/plain;charset=utf-8' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = name
    a.click()
    setTimeout(() => URL.revokeObjectURL(url), 0)
}

export function downloadDataUrl(name: string, dataUrl: string) {
    const a = document.createElement('a')
    a.href = dataUrl
    a.download = name
    a.click()
}
