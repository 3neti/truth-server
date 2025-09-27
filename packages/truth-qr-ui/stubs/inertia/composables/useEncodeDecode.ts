import useTruthQr from './useTruthQr'

type FormShape = {
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
    writer?: string // prebuilt spec
}

export default function useEncodeDecode() {
    const { encode, decode, encodeResult, decodeResult, loading, error } = useTruthQr()

    async function encodeWith(form: FormShape) {
        return encode({
            payload: form.payload,
            code: form.code,
            envelope: form.envelope,
            prefix: form.prefix,
            version: form.version,
            transport: form.transport,
            serializer: form.serializer,
            by: form.by,
            size: form.size,
            count: form.count,
            include_qr: form.include_qr,
            writer: form.writer,
        } as any)
    }

    async function decodeWith(form: FormShape, lines: string[]) {
        return decode({
            lines,
            envelope: form.envelope,
            prefix: form.prefix,
            version: form.version,
            transport: form.transport,
            serializer: form.serializer,
        } as any)
    }

    return { encodeWith, decodeWith, encodeResult, decodeResult, loading, error }
}
