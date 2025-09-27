import { ref } from 'vue'

type EncodeArgs = {
    payload: unknown
    code?: string
    envelope?: 'v1url' | 'v1line'
    prefix?: string       // UI name
    version?: string      // UI name
    transport?: string
    serializer?: string
    by?: 'size' | 'count'
    size?: number
    count?: number

    // NEW: writer / QR options (all optional)
    include_qr?: boolean
    writer?: string            // alias spec, e.g. "bacon(svg,size=256,margin=8)" or "endroid(png,size=256)"
    writer_fqcn?: string       // advanced fallback
    writer_fmt?: 'svg' | 'png' | 'eps'
    writer_size?: number
    writer_margin?: number
}

type DecodeArgs = {
    lines?: string[]
    chunks?: { text: string }[]
    envelope?: 'v1url' | 'v1line'
    prefix?: string       // UI name
    version?: string      // UI name
    transport?: string
    serializer?: string
}

export default function useTruthQr() {
    const encodeResult = ref<any>(null)
    const decodeResult = ref<any>(null)
    const loading = ref(false)
    const error = ref<string | null>(null)

    const routes = {
        encode: (window as any)?.TRUTH_QR_ENCODE_URL ?? '/api/encode',
        decode: (window as any)?.TRUTH_QR_DECODE_URL ?? '/api/decode',
    }

    async function encode(args: EncodeArgs) {
        error.value = null
        loading.value = true
        try {
            const body: Record<string, unknown> = {
                payload: args.payload,
                code: args.code,
                envelope: args.envelope,                      // alias
                envelope_prefix: args.prefix || undefined,    // map UI → controller
                envelope_version: args.version || undefined,  // map UI → controller
                transport: args.transport,
                serializer: args.serializer,
                by: args.by,
                size: args.size,
                count: args.count,
            }

            // NEW: only include writer fields when include_qr is true
            if (args.include_qr) {
                body.include_qr = true

                if (args.writer && args.writer.trim() !== '') {
                    body.writer = args.writer
                } else if (args.writer_fqcn && args.writer_fqcn.trim() !== '') {
                    body.writer_fqcn = args.writer_fqcn
                    if (args.writer_fmt)   body.writer_fmt   = args.writer_fmt
                    if (args.writer_size)  body.writer_size  = args.writer_size
                    if (args.writer_margin !== undefined) body.writer_margin = args.writer_margin
                }
            }

            const res = await fetch(routes.encode, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(body),
            })
            const raw = await res.text()
            let json: any = null
            try {
                json = raw ? JSON.parse(raw) : null
            } catch {
                // not JSON; keep raw HTML/text for diagnosis
            }
            if (!res.ok) {
                // prefer JSON error; otherwise show first 200 chars of raw
                const msg = (json && json.error) ? json.error : (raw?.slice(0, 200) || 'Request failed')
                throw new Error(msg)
            }
            encodeResult.value = json
            // const json = await res.json()
            // if (!res.ok) throw new Error(json?.error || 'Encode failed')
            encodeResult.value = json
            return json
        } catch (e: any) {
            error.value = e?.message ?? String(e)
            throw e
        } finally {
            loading.value = false
        }
    }

    async function decode(args: DecodeArgs) {
        error.value = null
        loading.value = true
        try {
            const res = await fetch(routes.decode, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({
                    lines: args.lines,
                    chunks: args.chunks,
                    envelope: args.envelope,                      // alias
                    envelope_prefix: args.prefix || undefined,    // map UI → controller
                    envelope_version: args.version || undefined,  // map UI → controller
                    transport: args.transport,
                    serializer: args.serializer,
                }),
            })
            const json = await res.json()
            if (!res.ok) throw new Error(json?.error || 'Decode failed')
            decodeResult.value = json
            return json
        } catch (e: any) {
            error.value = e?.message ?? String(e)
            throw e
        } finally {
            loading.value = false
        }
    }

    return { encode, decode, encodeResult, decodeResult, loading, error, routes }
}
