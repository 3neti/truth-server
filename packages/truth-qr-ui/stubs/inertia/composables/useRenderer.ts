export function useRenderer() {
    async function render(options: {
        templateName: string
        data: object
        format?: 'pdf' | 'html' | 'md'
        filename?: string
        download?: boolean
        openInNewTab?: boolean
        partials?: Record<string, string>
        schema?: object
        engineFlags?: object
        paperSize?: string
        orientation?: 'portrait' | 'landscape'
        assetsBaseUrl?: string
    }) {
        const {
            templateName,
            data,
            format = 'pdf',
            filename = 'document',
            download = true,
            openInNewTab = false,
            partials,
            schema,
            engineFlags,
            paperSize,
            orientation,
            assetsBaseUrl,
        } = options

        try {
            const res = await fetch(route('truth-render'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    templateName,
                    format,
                    data,
                    partials,
                    schema,
                    engineFlags,
                    paperSize,
                    orientation,
                    assetsBaseUrl,
                }),
            })

            if (!res.ok) {
                const errorText = await res.text()
                console.error('Render error:', errorText)
                alert('Failed to render document.')
                return
            }

            const blob = await res.blob()
            const url = URL.createObjectURL(blob)

            if (openInNewTab) {
                window.open(url, '_blank')
            } else if (download) {
                const link = document.createElement('a')
                link.href = url
                link.download = `${filename}.${format}`
                link.click()
            }

            URL.revokeObjectURL(url)
        } catch (e) {
            console.error('Render failed:', e)
            alert('Something went wrong while rendering the document.')
        }
    }

    return { render }
}
