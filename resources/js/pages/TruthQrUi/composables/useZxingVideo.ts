import { ref, onBeforeUnmount } from 'vue'
import {
    BrowserMultiFormatReader,
    IScannerControls,
    Result,
} from '@zxing/browser'

type ZxingCallbacks = {
    onDetected?: (text: string, raw: Result) => void
    onStarted?: (deviceId?: string) => void
    onStopped?: () => void
}

export default function useZxingVideo(cb: ZxingCallbacks = {}) {
    const videoEl = ref<HTMLVideoElement | null>(null)
    const active = ref(false)

    const readerRef = ref<BrowserMultiFormatReader | null>(null)
    const controlsRef = ref<IScannerControls | null>(null)
    const streamRef = ref<MediaStream | null>(null)
    const currentDeviceId = ref<string | undefined>(undefined)

    async function stop() {
        console.log('[ZXING] stop() called')
        try {
            controlsRef.value?.stop()
            controlsRef.value = null
        } catch (e) {
            console.warn('[ZXING] controls.stop() error:', e)
        }

        try {
            streamRef.value?.getTracks().forEach(t => t.stop())
            streamRef.value = null
        } catch (e) {
            console.warn('[ZXING] tracks stop() error:', e)
        }

        try {
            if (videoEl.value) {
                videoEl.value.pause()
                videoEl.value.srcObject = null
            }
        } catch (e) {
            console.warn('[ZXING] video cleanup error:', e)
        }

        active.value = false
        cb.onStopped?.()
    }

    async function start(deviceId?: string) {
        console.log('[ZXING] start() called; preferred deviceId:', deviceId)
        if (!videoEl.value) {
            console.warn('[ZXING] No video element bound yet')
            return
        }

        // If already running, stop then restart
        if (active.value) {
            console.log('[ZXING] Already active → restarting')
            await stop()
        }

        const reader = (readerRef.value ??= new BrowserMultiFormatReader())

        // try to pick a back camera if none specified
        try {
            const devices = await BrowserMultiFormatReader.listVideoInputDevices()
            console.log('[ZXING] video devices:', devices)

            let chosenId = deviceId
            if (!chosenId) {
                const env = devices.find(d =>
                    /back|rear|environment/i.test(d.label || '')
                )
                chosenId = env?.deviceId || devices[0]?.deviceId
            }

            if (!chosenId) {
                console.warn('[ZXING] No camera devices found')
                return
            }

            currentDeviceId.value = chosenId
            cb.onStarted?.(chosenId)

            // Kick off the decoding loop
            console.log('[ZXING] decodeFromVideoDevice →', chosenId)
            const controls = await reader.decodeFromVideoDevice(
                chosenId,
                videoEl.value,
                (result, err, controls) => {
                    // Keep a handle to controls for stop()
                    if (!controlsRef.value) controlsRef.value = controls

                    if (result) {
                        const text = result.getText?.() ?? String(result)
                        console.log('[ZXING] detected text:', text)
                        cb.onDetected?.(text, result)
                    } else if (err) {
                        // NOTE: ZXing fires a lot of "NotFound" errors during scanning—this is normal.
                        // Avoid console spam unless debugging tracking issues:
                        // console.debug('[ZXING] decode tick (no result):', err?.name)
                    }
                }
            )

            // reader may resolve with controls immediately; also grab stream from <video>
            controlsRef.value = controls
            streamRef.value = (videoEl.value?.srcObject as MediaStream) || null
            active.value = true
        } catch (e: any) {
            console.error('[ZXING] start() failed:', e?.name || e, e)
            if (e?.name === 'NotAllowedError') {
                console.error('[ZXING] Camera permission denied by user')
            } else if (e?.name === 'NotFoundError') {
                console.error('[ZXING] No camera found on this device')
            }
            await stop()
        }
    }

    onBeforeUnmount(() => {
        console.log('[ZXING] onBeforeUnmount → stop')
        stop()
    })

    return {
        // state
        videoEl,
        active,
        currentDeviceId,

        // actions
        start,
        stop,
    }
}
