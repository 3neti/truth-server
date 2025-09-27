import { ref } from 'vue'
import useTruthQr from './useTruthQr'
import { parseIndexTotal } from './MultiPartTools'

export type GetDecodeArgs = () => {
    envelope?: 'v1url' | 'v1line'
    prefix?: string
    version?: string
    transport?: string
    serializer?: string
}

export default function useScannerSession(getArgs: GetDecodeArgs) {
    const modTag = '[scanner]'
    console.log(`${modTag} init: creating scanner session`)

    const { decode, decodeResult, error, loading } = useTruthQr()

    // unique buffer of lines
    const lines = ref<string[]>([])
    const lineSet = new Set<string>()

    // progress/status snapshot (mirrors backend response)
    const status = ref<{
        code: string
        total: number
        received: number
        missing: number[]
        complete: boolean
    }>({ code: '', total: 0, received: 0, missing: [], complete: false })

    function normalizeLine(raw: string): string {
        const out = (raw || '').trim()
        // only log small samples to avoid noise
        console.log(`${modTag} normalizeLine`, { rawLen: raw?.length ?? 0, outSample: out.slice(0, 64) })
        return out
    }

    function addScan(raw: string) {
        console.log(`${modTag} addScan() called`)
        const line = normalizeLine(raw)
        if (!line) {
            console.log(`${modTag} addScan: empty after trim, ignore`)
            return
        }
        if (!lineSet.has(line)) {
            lineSet.add(line)
            lines.value = Array.from(lineSet)
            const meta = parseIndexTotal(line)
            console.log(`${modTag} addScan: added`, {
                totalLines: lines.value.length,
                meta,
                sample: line.slice(0, 80),
            })
        } else {
            console.log(`${modTag} addScan: duplicate ignored`)
        }
    }

    function addMany(raw: string) {
        console.log(`${modTag} addMany()`, { rawLen: raw?.length ?? 0 })
        raw.split(/\r?\n/).forEach(addScan)
        console.log(`${modTag} addMany: now have`, lines.value.length, 'unique lines')
    }

    function remove(line: string) {
        console.log(`${modTag} remove()`, { sample: line.slice(0, 80) })
        if (lineSet.delete(line)) {
            lines.value = Array.from(lineSet)
            console.log(`${modTag} remove: removed; totalLines=`, lines.value.length)
        } else {
            console.log(`${modTag} remove: not found`)
        }
    }

    function clear() {
        console.log(`${modTag} clear()`)
        lineSet.clear()
        lines.value = []
        status.value = { code: '', total: 0, received: 0, missing: [], complete: false }
    }

    async function decodeNow() {
        console.log(`${modTag} decodeNow() start`, { count: lines.value.length })
        const args = getArgs?.() ?? {}
        console.log(`${modTag} decodeNow: args`, args)

        try {
            const res = await decode({
                lines: lines.value,
                envelope: args.envelope,
                prefix: args.prefix,
                version: args.version,
                transport: args.transport,
                serializer: args.serializer,
            } as any)

            // shape → status
            status.value = {
                code: String(res?.code ?? ''),
                total: Number(res?.total ?? 0),
                received: Number(res?.received ?? 0),
                missing: Array.isArray(res?.missing) ? res?.missing : [],
                complete: Boolean(res?.complete),
            }

            console.log(`${modTag} decodeNow: done`, { status: status.value })
            return res
        } catch (e) {
            console.error(`${modTag} decodeNow: ERROR`, e)
            throw e
        }
    }

    function simulateMissing() {
        console.log(`${modTag} simulateMissing()`)
        if (lines.value.length < 2) {
            console.log(`${modTag} simulateMissing: not enough lines`)
            return
        }

        const samples = lines.value
            .map((ln, idx) => ({ ln, meta: parseIndexTotal(ln), idx }))
            .filter(s => !!s.meta)

        if (!samples.length) {
            console.log(`${modTag} simulateMissing: no parseable samples`)
            return
        }

        const n = samples[0].meta!.n
        const mid = Math.floor((n + 1) / 2)
        const hit = lines.value.find(ln => parseIndexTotal(ln)?.i === mid)

        if (hit) {
            console.log(`${modTag} simulateMissing: dropping mid index`, { mid })
            remove(hit)
        } else {
            console.log(`${modTag} simulateMissing: mid index not present`, { mid })
        }
    }

    // Helpful live telemetry
    console.log(`${modTag} reactive state wired`, {
        hasDecode: typeof decode === 'function',
    })

    return {
        // state
        lines,
        status,
        decodeResult,
        error,
        loading,

        // actions
        addScan,
        addMany,
        remove,
        clear,
        decodeNow,
        simulateMissing,
    }
}
