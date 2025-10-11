import { ref } from 'vue'

type EncodeArgs = {
    payload: unknown
    code?: string
    envelope?: 'v1url' | 'v1line'
    prefix?: string
    version?: string
    transport?: string
    serializer?: string
    by?: 'size' | 'count'
    size?: number
    count?: number
    include_qr?: boolean
    writer?: string
    writer_fqcn?: string
    writer_fmt?: 'svg' | 'png' | 'eps'
    writer_size?: number
    writer_margin?: number
}

type DecodeArgs = {
    lines?: string[]
    chunks?: { text: string }[]
    envelope?: 'v1url' | 'v1line'
    prefix?: string
    version?: string
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
                envelope: args.envelope,
                envelope_prefix: args.prefix || undefined,
                envelope_version: args.version || undefined,
                transport: args.transport,
                serializer: args.serializer,
                by: args.by,
                size: args.size,
                count: args.count,
            }

            if (args.include_qr) {
                body.include_qr = true
                if (args.writer && args.writer.trim() !== '') {
                    body.writer = args.writer
                } else if (args.writer_fqcn && args.writer_fqcn.trim() !== '') {
                    body.writer_fqcn = args.writer_fqcn
                    if (args.writer_fmt) body.writer_fmt = args.writer_fmt
                    if (args.writer_size) body.writer_size = args.writer_size
                    if (args.writer_margin !== undefined) body.writer_margin = args.writer_margin
                }
            }

            console.log('[useTruthQr] encode endpoint', routes.encode)
            console.log('[useTruthQr] encode body preview', { ...body, payload: '[omitted]' })

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
                console.warn('[useTruthQr] encode response not JSON')
            }
            if (!res.ok) {
                const msg = (json && json.error) ? json.error : (raw?.slice(0, 200) || 'Request failed')
                throw new Error(msg)
            }
            encodeResult.value = json
            console.log('[useTruthQr] encode result', json)
            return json
        } catch (e: any) {
            error.value = e?.message ?? String(e)
            console.error('[useTruthQr] encode ERROR', e)
            throw e
        } finally {
            loading.value = false
        }
    }

    async function decode(args: DecodeArgs) {
        error.value = null
        loading.value = true
        try {
            console.log('[useTruthQr] decode endpoint', routes.decode)
            console.log('[useTruthQr] decode args preview', {
                ...args,
                lines: args.lines?.length,
                chunks: args.chunks?.length,
            })

            const res = await fetch(routes.decode, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({
                    lines: args.lines,
                    chunks: args.chunks,
                    envelope: args.envelope,
                    envelope_prefix: args.prefix || undefined,
                    envelope_version: args.version || undefined,
                    transport: args.transport,
                    serializer: args.serializer,
                }),
            })

            const text = await res.text()
            let json: any = null
            try {
                json = text ? JSON.parse(text) : null
            } catch {
                console.warn('[useTruthQr] decode response not JSON', text?.slice(0, 200))
            }

            if (!res.ok) {
                throw new Error(json?.error || text?.slice(0, 200) || 'Decode failed')
            }

            decodeResult.value = json
            console.log('[useTruthQr] decode result', json)
            return json
        } catch (e: any) {
            error.value = e?.message ?? String(e)
            console.error('[useTruthQr] decode ERROR', e)
            throw e
        } finally {
            loading.value = false
        }
    }

    return { encode, decode, encodeResult, decodeResult, loading, error, routes }
}
