<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import axios from 'axios'
import useZxingVideo from './composables/useZxingVideo'
import { parseIndexTotal } from './composables/MultiPartTools'

// State
const chunks = ref('')
const loading = ref(false)
const error = ref('')
const result = ref<any>(null)
const decodedPayload = ref<any>(null)
const copyStatus = ref('')
const pdfLoading = ref(false)
const pdfError = ref('')
const pdfUrl = ref('')
const pdfBlob = ref<Blob | null>(null)

// Scanner state
const scannerMode = ref<'manual' | 'keyboard' | 'camera'>('manual')
const scannerActive = ref(false)
const scannedChunks = ref<Set<string>>(new Set())
const scanningBuffer = ref('')
const lastScanTime = ref(0)
const scanProgress = ref({ received: 0, total: 0, complete: false })

// Camera scanner state
const { videoEl, active: cameraActive, start: startCamera, stop: stopCamera } = useZxingVideo({
  onDetected: (text) => {
    console.log('📷 Camera detected:', text)
    
    // Check if it looks like a TRUTH chunk
    const looksTruthy = /^truth:\/\//i.test(text) || /[?&]c=/.test(text) || !!parseIndexTotal(text) || /^ER\|/.test(text)
    if (!looksTruthy) {
      console.log('📷 Ignoring non-TRUTH chunk:', text)
      return
    }
    
    processScannedChunk(text)
  },
  onStarted: (deviceId) => {
    console.log('📷 Camera started:', deviceId)
    scannerActive.value = true
  },
  onStopped: () => {
    console.log('📷 Camera stopped')
    scannerActive.value = false
  }
})

// Simple decode function
async function decode() {
  if (!chunks.value.trim()) {
    error.value = 'Please enter some chunks'
    return
  }

  loading.value = true
  error.value = ''
  result.value = null
  decodedPayload.value = null

  try {
    // Split chunks by newlines and clean up
    const lines = chunks.value
      .split('\n')
      .map(line => line.trim())
      .filter(line => line.length > 0)

    console.log('Sending lines:', lines)

    // Call decode API
    const response = await axios.post('/api/decode', {
      lines: lines,
      envelope: 'v1line',
      transport: 'base64url+deflate',
      serializer: 'json'
    })

    console.log('Decode response:', response.data)
    result.value = response.data

    if (response.data.complete && response.data.payload) {
      decodedPayload.value = response.data.payload
      // Auto-generate PDF when decoding is complete
      await generatePdf()
    }

  } catch (e: any) {
    console.error('Decode error:', e)
    error.value = e.response?.data?.error || e.message || 'Decode failed'
  } finally {
    loading.value = false
  }
}

function clear() {
  chunks.value = ''
  result.value = null
  decodedPayload.value = null
  error.value = ''
  copyStatus.value = ''
  pdfError.value = ''
  // Clean up PDF resources
  if (pdfUrl.value) {
    window.URL.revokeObjectURL(pdfUrl.value)
    pdfUrl.value = ''
  }
  pdfBlob.value = null
  
  // Reset scanner state
  scannedChunks.value.clear()
  scanningBuffer.value = ''
  scanProgress.value = { received: 0, total: 0, complete: false }
  
  // Stop camera if active
  if (cameraActive.value) {
    stopCamera()
  }
}

// Copy to clipboard function
async function copyToClipboard() {
  if (!decodedPayload.value) return
  
  try {
    const jsonString = JSON.stringify(decodedPayload.value, null, 2)
    await navigator.clipboard.writeText(jsonString)
    copyStatus.value = 'Copied!'
    
    // Clear status after 2 seconds
    setTimeout(() => {
      copyStatus.value = ''
    }, 2000)
  } catch (err) {
    console.error('Failed to copy:', err)
    copyStatus.value = 'Copy failed'
    setTimeout(() => {
      copyStatus.value = ''
    }, 2000)
  }
}

// PDF generation function (for auto-generation)
async function generatePdf() {
  if (!decodedPayload.value) return
  
  pdfLoading.value = true
  pdfError.value = ''
  
  try {
    console.log('Generating PDF with payload:', decodedPayload.value)
    
    // Call the truth-render API endpoint
    const response = await axios.post('/truth/render', {
      templateName: 'core:precinct/er/template',
      data: decodedPayload.value,
      format: 'pdf',
      paperSize: 'A4',
      orientation: 'portrait',
      filename: 'election_return'
    }, {
      responseType: 'blob' // Important for PDF handling
    })
    
    // Store blob and create URL for inline display
    const blob = new Blob([response.data], { type: 'application/pdf' })
    pdfBlob.value = blob
    
    // Clean up previous URL if exists
    if (pdfUrl.value) {
      window.URL.revokeObjectURL(pdfUrl.value)
    }
    
    pdfUrl.value = window.URL.createObjectURL(blob)
    
    console.log('PDF generated successfully')
    
  } catch (err: any) {
    console.error('PDF generation error:', err)
    pdfError.value = err.response?.data?.error || err.message || 'PDF generation failed'
  } finally {
    pdfLoading.value = false
  }
}

// Share/download PDF function
function sharePdf() {
  if (!pdfBlob.value || !decodedPayload.value) return
  
  // Create temporary link and trigger download
  const url = window.URL.createObjectURL(pdfBlob.value)
  const link = document.createElement('a')
  link.href = url
  link.download = `election_return_${decodedPayload.value.code || 'decoded'}.pdf`
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  
  // Clean up temporary URL
  window.URL.revokeObjectURL(url)
  
  console.log('PDF shared/downloaded successfully')
}

// Scanner Mode Functions
function toggleScannerMode(mode: 'manual' | 'keyboard' | 'camera') {
  // Stop all active scanners first
  if (scannerActive.value) {
    stopKeyboardScanner()
  }
  if (cameraActive.value) {
    stopCamera()
  }
  
  scannerMode.value = mode
  
  // Start the selected scanner
  if (mode === 'keyboard') {
    startKeyboardScanner()
  } else if (mode === 'camera') {
    startCameraScanner()
  }
}

function startKeyboardScanner() {
  scannerActive.value = true
  scannedChunks.value.clear()
  scanProgress.value = { received: 0, total: 0, complete: false }
  console.log('🔍 Keyboard scanner started - Ready to scan QR codes!')
}

function stopKeyboardScanner() {
  scannerActive.value = false
  scanningBuffer.value = ''
  console.log('⏹️ Keyboard scanner stopped')
}

function startCameraScanner() {
  scannedChunks.value.clear()
  scanProgress.value = { received: 0, total: 0, complete: false }
  startCamera()
  console.log('📷 Camera scanner starting...')
}

function stopCameraScanner() {
  stopCamera()
  console.log('📷 Camera scanner stopped')
}

// Handle keyboard input from QR scanner
function handleKeyboardInput(event: KeyboardEvent) {
  if (!scannerActive.value) return
  
  const currentTime = Date.now()
  
  // Detect Enter/Return key (end of QR scan)
  if (event.key === 'Enter' || event.keyCode === 13) {
    event.preventDefault()
    
    if (scanningBuffer.value.trim()) {
      processScannedChunk(scanningBuffer.value.trim())
      scanningBuffer.value = ''
    }
    return
  }
  
  // Filter out modifier keys and control characters
  const ignoredKeys = [
    'Shift', 'Control', 'Alt', 'Meta', 'CapsLock', 'Tab', 'Escape',
    'ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'Backspace', 'Delete',
    'Home', 'End', 'PageUp', 'PageDown', 'Insert', 'F1', 'F2', 'F3', 'F4', 
    'F5', 'F6', 'F7', 'F8', 'F9', 'F10', 'F11', 'F12'
  ]
  
  // Skip if it's a modifier key or control character
  if (ignoredKeys.includes(event.key) || event.key.length > 1) {
    return
  }
  
  // Skip if modifier keys are pressed (except for normal character input)
  if (event.ctrlKey || event.altKey || event.metaKey) {
    return
  }
  
  // Only add printable characters
  if (event.key.length === 1) {
    scanningBuffer.value += event.key
    lastScanTime.value = currentTime
    
    console.log(`📝 Added character: '${event.key}' (buffer: ${scanningBuffer.value.length} chars)`)
  }
}

// Process a scanned chunk
function processScannedChunk(chunkData: string) {
  console.log('📱 Scanned chunk:', chunkData)
  
  // Very relaxed validation - just check if it looks like it has some structure
  if (!chunkData || chunkData.length < 10) {
    console.warn('⚠️ Chunk too short:', chunkData)
    return
  }
  
  let currentPart = 1, totalParts = 1
  
  // Try to extract part numbers from various formats
  // URL format: truth://v1/TRUTH/code/part/total?c=data
  if (chunkData.includes('://')) {
    try {
      const url = new URL(chunkData)
      const pathParts = url.pathname.split('/').filter(p => p.length > 0)
      if (pathParts.length >= 4) {
        currentPart = parseInt(pathParts[3]) || 1
        totalParts = parseInt(pathParts[4]) || 1
        console.log(`📊 URL format detected - Part: ${currentPart}/${totalParts}`)
      }
    } catch (e) {
      console.log('📊 URL parsing failed, treating as single chunk')
    }
  }
  // Pipe format: ER|v1|code|part/total|data
  else if (chunkData.includes('|') && chunkData.includes('/')) {
    const parts = chunkData.split('|')
    for (const part of parts) {
      if (part.includes('/')) {
        const [p, t] = part.split('/').map(Number)
        if (!isNaN(p) && !isNaN(t)) {
          currentPart = p
          totalParts = t
          console.log(`📊 Pipe format detected - Part: ${currentPart}/${totalParts}`)
          break
        }
      }
    }
  }
  
  // Check for duplicates
  if (scannedChunks.value.has(chunkData)) {
    console.log('🔄 Duplicate chunk detected, ignoring')
    return
  }
  
  // Add chunk to collection
  scannedChunks.value.add(chunkData)
  console.log(`✅ Added chunk ${scannedChunks.value.size} to collection`)
  
  // Update progress (use the detected total, or just show current count)
  scanProgress.value = {
    received: scannedChunks.value.size,
    total: totalParts > 1 ? totalParts : scannedChunks.value.size,
    complete: totalParts > 1 ? (scannedChunks.value.size === totalParts) : false
  }
  
  console.log(`📊 Progress: ${scanProgress.value.received}/${scanProgress.value.total} chunks`)
  
  // Update chunks textarea
  chunks.value = Array.from(scannedChunks.value).join('\n')
  
  // Auto-decode if we think we have all chunks (only if totalParts > 1)
  if (scanProgress.value.complete && totalParts > 1) {
    console.log('✅ All chunks received! Auto-decoding...')
    setTimeout(() => decode(), 500) // Small delay to ensure UI updates
  }
}

// Lifecycle hooks
onMounted(() => {
  document.addEventListener('keydown', handleKeyboardInput)
})

onUnmounted(() => {
  document.removeEventListener('keydown', handleKeyboardInput)
})

// Sample data for testing - only first 2 chunks
function loadSample() {
  chunks.value = `ER|v1|317537|1/6|7Z1bc-LIkoD_SoVfdjdOewIwbl_eAMlgjDAhNOM5s9tBlFHZ1LFQOSRhNz0x_31T2IjUzRc1I2tMvsw0BpRJqpSVlV9l1p9719xxVODvnf7vn3tTZYu90712a7Bfq9X2vuzde2Iq3WkweX6n86tpnhutS3i7Dm8_qEA8f5O7trT5-iV3JId_7Q00-NTzdwfa5OlbLp-vXgvlcs9WTJMdfu9JFcpTvgykcvdOI2VGpj4-1_ShtbrSwg32Tutf9hzxIBx41-Xhx7mzuezIE760hRswdcOCmWCjmXTk_b10Qbe__vpWhpAvuQaxehuDWL24QSw1Zz3u3vnZhvjtvKPvv1fR3-QUlCtikr9NXL5x-mi09BOjpa9mrrtkmri_zzbPWB-2rEsTKdp4SdOxgL8pL1fFtU4da6NTx5rU6rXNNToz7jnyh2DWTHigTJl6XZgbvS5M0AvZ6kJwd8FMAUL8UpUaXmyUGl6AUo3NNYZyqhzBLqQ95-WaSh9vtNLHMKyQVvp8ztk4UK4oVSXjbKOScQYqHWyuYSjvlrvszBOibEv1B-gBHIBaTfQACteVN8JjA_7oCXdarsE6bfQYtkGzQ_wYetIPJNiszZ1y1RojtcahWl_RNcDJeZy1FzC_Tu9KVesKjfircMQfba5xJR2HjecymJU7tJC_6oO_qh2jobWADzFTXQsvKNdhGWjGMcIZ5wQ9hzwImMbnZTj2b2X86JxZVx8i9ziMz7q6_QghGhsqL8gzQ_fyN90cXpr754NhZpQAox9r2VUPwnNBzf9bNGr1JjuH95W_EiFyLbFlIfnGGF6hGewqbowhV3PJrmBYvBShvVfVVchUyCh_m7h885jIPGbCPBBv-IJdgWMRnn-v8sZL-7JlavuGbrT1hNKNXKVHnnqAZYjkDmurcEAaYg7OIl_9tb495J977fjU34OFj2Bt4XnLj9L028dZKC8ER4FJ_ywZgttSsDPlB8LLtpiphwsGWC60rPPf9FD3_XrmoLRhwvbkNNhcXHv-C4StsOj0YfUA_u1BZP0A9t91Hzzz8xf-J9eOH6VNvnXbnY112524ddswLy9Zx1vYi5wVjtH699Nzvr9ehr_lgTcWrpzKexgYBl9uHvnOwvPknKsvbxuT5ch-YZJC4YNuJiepcFyaAhYWy7w4vnP567BzPsj9Dce5vwHCuNtb-B3wP4gvlxBl
ER|v1|317537|2/6|xh-tl35MFCZ2UZjYjbuhcSDuZ8JlXY_P-LyS2iPbj834YmXMlfTA7ZsQirhVVN5Aj5zRiS9pDO6BqqyjAnj2wGlWUn8Uyhvj-MLHkNMZFxDNz7jrqkqav6OjdZseXyB1ZvJRBKC__h-pbpRXRf27Bhr88CK-luoubhfMuObBjO-b_LGKP0C73OivXcbXXRp_kDa7XApHParKKP-tMoqs5iOcEq9vISXe6m9uSKsffyJa7q1wpMtZXzlSUD58x_PhFUxcDlCyYDCc1JrInQwW9zLgbLhU7u1_qVLVarXQQ9Wa1OpIrdZ8yVo2n5ebWeqikK8LIV8dPeZdCIi73FZBqRolFx91FEm0hSvscL3TWYSuEWa06WwnE3HVTqIaaJgbMMwPcDDIw7zLjDuctRxJWfDTvbPRRq2zEVgLOYUzR60gBhstbsu9h5doWF3CsGogrS6nAYRk4NfvQ928nUVSH5kOh89O5jzw5HccmegTo2WZ57_HA2gLrmusP_t6BmrUMq1_7w_Ox28Oq7gXLPcd6ScTUW_NeW1VIiGEN6WqUg9SI71iP-O-fy1cO-8Z__wkQUPOWRvF8zKaeGAj-IZTAYLQ1tAGEHgRz8BA3CSnUjEN7qmlPEUggUBCGSBBRxxOv4pP1vpcOssQU_p5_naXQUL_AsVf8CIxMn-5-IWN5RzWGi9lHX72R6xc589YsWwtCMwQmCEwQ2CGwAyBmSqCmQaBmS2DmRYCM61e0hquYL3w4Xrky50tVkDzVx_mr-Yh3ivt-3LKWWfG_YBLl5LEYBMD2cuY1BrIXfc5rFWYoeaKExKJ9lqi2NSE2LSBkIgZesuu8sEn3VJOvYL4r4qlTJUsGaoqDtER09LboFcjmWFpO6G4Uoc5GuUw4aAFU-vx7pHfQIDyydkM-OQAYRl9qFtgCFSSZz1_gmAMwZjVKgtVJGoX8RSJxl0Ji9wL7iwWS76rIKbiJRJf_mHFJ9UjMRoKcrVEok0Ls2wzwe28ukkCMVTR8TeBmGStcQOvBz1hs4EIFEEYgjAEYQjCEIQhCEMQptoQ5mALEEZDMb6W2NiiCfcHPA9X3J9J9zZ3Afu5OIypISwFL-I-7qlnBtPUoyuWrO_9srM0BsUQ_bDcIkYX7mB9vISlrDvj3KFGP-myq-ZRouxKwZTv2uVWESCdxqDTQQ17KWfOWY8vxR2xtKo200n2`
}
</script>

<template>
  <div class="max-w-4xl mx-auto p-6 space-y-6">
    <!-- HEADER -->
    <header class="border-b pb-4">
      <h1 class="text-2xl font-bold text-gray-900">🔓 TRUTH Decoder</h1>
      <p class="text-gray-600 mt-1">Paste encoded chunks to reconstruct election return JSON</p>
    </header>

    <!-- MODE SELECTOR -->
    <div class="border-b pb-4">
      <div class="flex items-center gap-4">
        <span class="text-sm font-medium text-gray-700">Input Mode:</span>
        <div class="flex bg-gray-100 rounded-lg p-1">
          <button
            @click="toggleScannerMode('manual')"
            :class="[
              'px-3 py-1 text-sm rounded-md transition-all',
              scannerMode === 'manual' 
                ? 'bg-white shadow text-blue-600 font-medium' 
                : 'text-gray-600 hover:text-gray-800'
            ]"
          >
            ✏️ Manual Entry
          </button>
          <button
            @click="toggleScannerMode('keyboard')"
            :class="[
              'px-3 py-1 text-sm rounded-md transition-all',
              scannerMode === 'keyboard' 
                ? 'bg-white shadow text-green-600 font-medium' 
                : 'text-gray-600 hover:text-gray-800'
            ]"
          >
            ⌨️ QR Scanner
          </button>
          <button
            @click="toggleScannerMode('camera')"
            :class="[
              'px-3 py-1 text-sm rounded-md transition-all',
              scannerMode === 'camera' 
                ? 'bg-white shadow text-purple-600 font-medium' 
                : 'text-gray-600 hover:text-gray-800'
            ]"
          >
            📷 Camera
          </button>
        </div>
      </div>
    </div>

    <!-- KEYBOARD SCANNER STATUS -->
    <div v-if="scannerMode === 'keyboard'" class="p-4 bg-green-50 border border-green-200 rounded-md">
      <div class="flex items-center justify-between mb-2">
        <h3 class="text-lg font-semibold text-green-800">🔍 QR Scanner Mode</h3>
        <div class="flex items-center gap-2">
          <div :class="[
            'w-3 h-3 rounded-full',
            scannerActive ? 'bg-green-500 animate-pulse' : 'bg-gray-400'
          ]"></div>
          <span class="text-sm" :class="scannerActive ? 'text-green-700' : 'text-gray-600'">
            {{ scannerActive ? 'Ready to Scan' : 'Inactive' }}
          </span>
        </div>
      </div>
      
      <div class="text-sm text-green-700 mb-3">
        📱 Point your QR scanner at the election return QR codes. Each scan will be automatically processed.
      </div>
      
      <!-- Scanning Progress -->
      <div v-if="scanProgress.total > 0" class="space-y-2">
        <div class="flex items-center justify-between text-sm">
          <span class="text-green-700">Progress:</span>
          <span class="font-mono text-green-800">{{ scanProgress.received }}/{{ scanProgress.total }} chunks</span>
        </div>
        <div class="w-full bg-green-200 rounded-full h-2">
          <div 
            class="bg-green-600 h-2 rounded-full transition-all duration-300"
            :style="{ width: scanProgress.total > 0 ? (scanProgress.received / scanProgress.total * 100) + '%' : '0%' }"
          ></div>
        </div>
        <div v-if="scanProgress.complete" class="text-sm text-green-800 font-medium">
          ✅ All chunks received! Auto-decoding...
        </div>
      </div>
    </div>

    <!-- CAMERA SCANNER STATUS -->
    <div v-if="scannerMode === 'camera'" class="p-4 bg-purple-50 border border-purple-200 rounded-md">
      <div class="flex items-center justify-between mb-2">
        <h3 class="text-lg font-semibold text-purple-800">📷 Camera Scanner Mode</h3>
        <div class="flex items-center gap-2">
          <div :class="[
            'w-3 h-3 rounded-full',
            cameraActive ? 'bg-purple-500 animate-pulse' : 'bg-gray-400'
          ]"></div>
          <span class="text-sm" :class="cameraActive ? 'text-purple-700' : 'text-gray-600'">
            {{ cameraActive ? 'Camera Active' : 'Camera Off' }}
          </span>
        </div>
      </div>
      
      <div class="text-sm text-purple-700 mb-3">
        📱 Position QR codes in front of your camera. Detected chunks will be processed automatically.
      </div>
      
      <!-- Camera Video Preview -->
      <div class="mb-3 rounded-lg overflow-hidden border border-purple-200">
        <video 
          ref="videoEl" 
          class="w-full h-64 bg-black object-cover" 
          autoplay 
          playsinline 
          muted
          :class="{ 'opacity-50': !cameraActive }"
        />
      </div>
      
      <!-- Scanning Progress -->
      <div v-if="scanProgress.total > 0" class="space-y-2">
        <div class="flex items-center justify-between text-sm">
          <span class="text-purple-700">Progress:</span>
          <span class="font-mono text-purple-800">{{ scanProgress.received }}/{{ scanProgress.total }} chunks</span>
        </div>
        <div class="w-full bg-purple-200 rounded-full h-2">
          <div 
            class="bg-purple-600 h-2 rounded-full transition-all duration-300"
            :style="{ width: scanProgress.total > 0 ? (scanProgress.received / scanProgress.total * 100) + '%' : '0%' }"
          ></div>
        </div>
        <div v-if="scanProgress.complete" class="text-sm text-purple-800 font-medium">
          ✅ All chunks received! Auto-decoding...
        </div>
      </div>
    </div>

    <!-- INPUT SECTION -->
    <div class="space-y-4">
      <div>
        <label for="chunks" class="block text-sm font-medium text-gray-700 mb-2">
          {{ 
            scannerMode === 'manual' 
              ? 'Encoded Chunks (one per line):' 
              : scannerMode === 'keyboard'
              ? 'Scanned Chunks (Keyboard):'
              : 'Scanned Chunks (Camera):'
          }}
        </label>
        <textarea
          id="chunks"
          v-model="chunks"
          :readonly="scannerMode === 'keyboard' || scannerMode === 'camera'"
          rows="8"
          :class="[
            'w-full p-3 border rounded-md shadow-sm font-mono text-xs',
            scannerMode === 'keyboard' || scannerMode === 'camera'
              ? 'bg-gray-50 border-gray-200 text-gray-700' 
              : 'border-gray-300 focus:ring-blue-500 focus:border-blue-500'
          ]"
          :placeholder="
            scannerMode === 'manual' 
              ? 'Paste your encoded chunks here, one per line...\nExample:\nER|v1|317537|1/6|7Z1bc-LIkoD_SoVf...\nER|v1|317537|2/6|xh-tl35MFCZ2UZjY...'
              : scannerMode === 'keyboard'
              ? 'QR scanner chunks will appear here automatically...'
              : 'Camera-detected chunks will appear here automatically...'
          "
        ></textarea>
      </div>

      <!-- CONTROLS -->
      <div class="flex gap-3 flex-wrap">
        <!-- Manual Mode Controls -->
        <template v-if="scannerMode === 'manual'">
          <button
            @click="decode"
            :disabled="loading || !chunks.trim()"
            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {{ loading ? '🔄 Decoding...' : '🔓 Decode' }}
          </button>
          
          <button
            @click="loadSample"
            class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700"
          >
            📝 Load Sample (2/6)
          </button>
        </template>
        
        <!-- Scanner Mode Controls -->
        <template v-if="scannerMode === 'keyboard'">
          <button
            v-if="!scannerActive"
            @click="startKeyboardScanner"
            class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700"
          >
            🔍 Start Scanner
          </button>
          
          <button
            v-if="scannerActive"
            @click="stopKeyboardScanner"
            class="px-4 py-2 bg-orange-600 text-white rounded-md hover:bg-orange-700"
          >
            ⏹️ Stop Scanner
          </button>
          
          <button
            @click="decode"
            :disabled="loading || !chunks.trim()"
            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {{ loading ? '🔄 Decoding...' : '🔓 Manual Decode' }}
          </button>
        </template>
        
        <!-- Camera Mode Controls -->
        <template v-if="scannerMode === 'camera'">
          <button
            v-if="!cameraActive"
            @click="startCameraScanner"
            class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700"
          >
            📷 Start Camera
          </button>
          
          <button
            v-if="cameraActive"
            @click="stopCameraScanner"
            class="px-4 py-2 bg-orange-600 text-white rounded-md hover:bg-orange-700"
          >
            ⏹️ Stop Camera
          </button>
          
          <button
            @click="decode"
            :disabled="loading || !chunks.trim()"
            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {{ loading ? '🔄 Decoding...' : '🔓 Manual Decode' }}
          </button>
        </template>
        
        <!-- Common Controls -->
        <button
          @click="clear"
          class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700"
        >
          🗑️ Clear
        </button>
      </div>
    </div>

    <!-- ERROR -->
    <div v-if="error" class="p-4 bg-red-50 border border-red-200 rounded-md">
      <p class="text-red-800">❌ {{ error }}</p>
    </div>

    <!-- RESULTS -->
    <div v-if="result" class="space-y-4">
      <!-- DECODE STATUS -->
      <div class="p-4 bg-gray-50 border rounded-md">
        <h3 class="text-lg font-semibold mb-2">📊 Decode Status</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
          <div>
            <span class="text-gray-600">Code:</span>
            <span class="font-mono ml-2">{{ result.code }}</span>
          </div>
          <div>
            <span class="text-gray-600">Received:</span>
            <span class="font-mono ml-2">{{ result.received }}/{{ result.total }}</span>
          </div>
          <div>
            <span class="text-gray-600">Complete:</span>
            <span class="ml-2" :class="result.complete ? 'text-green-600' : 'text-orange-600'">
              {{ result.complete ? '✅ Yes' : '⏳ No' }}
            </span>
          </div>
          <div v-if="!result.complete">
            <span class="text-gray-600">Missing:</span>
            <span class="font-mono ml-2 text-red-600">{{ result.missing?.join(', ') }}</span>
          </div>
        </div>
        
        <!-- TRANSFORMATION INFO -->
        <div v-if="result.transformed" class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-md">
          <h4 class="text-sm font-semibold text-blue-800 mb-2">🔄 Auto-Transformation Applied</h4>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
            <div>
              <span class="text-blue-600">From:</span>
              <span class="ml-2 font-mono text-blue-800">{{ result.transformation?.from }}</span>
            </div>
            <div>
              <span class="text-blue-600">To:</span>
              <span class="ml-2 font-mono text-blue-800">{{ result.transformation?.to }}</span>
            </div>
            <div>
              <span class="text-blue-600">Expansion:</span>
              <span class="ml-2 font-mono text-blue-800">{{ result.transformation?.compression }}</span>
            </div>
          </div>
          <div class="mt-2 text-xs text-blue-700">
            💡 Minified ERData was automatically expanded to full ElectionReturnData for PDF generation.
          </div>
        </div>
      </div>

      <!-- DECODED PAYLOAD -->
      <div v-if="decodedPayload" class="space-y-3">
        <h3 class="text-lg font-semibold">🎯 Decoded Election Return</h3>
        
        <!-- Quick Stats -->
        <div v-if="decodedPayload.precinct" class="p-4 bg-green-50 border border-green-200 rounded-md">
          <h4 class="font-semibold text-green-800 mb-2">Election Summary</h4>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
            <div>
              <span class="text-gray-600">Precinct:</span>
              <span class="ml-2 font-mono">{{ decodedPayload.precinct?.code }}</span>
            </div>
            <div v-if="decodedPayload.tallies">
              <span class="text-gray-600">Tallies:</span>
              <span class="ml-2">{{ decodedPayload.tallies.length }} positions</span>
            </div>
            <div v-if="decodedPayload.code">
              <span class="text-gray-600">ER Code:</span>
              <span class="ml-2 font-mono">{{ decodedPayload.code }}</span>
            </div>
          </div>
        </div>

        <!-- Raw JSON -->
        <div>
          <div class="flex items-center justify-between mb-2">
            <h4 class="font-semibold">📋 Full JSON Data</h4>
            <div class="flex items-center gap-2">
              <span v-if="copyStatus" class="text-sm" :class="copyStatus === 'Copied!' ? 'text-green-600' : 'text-red-600'">
                {{ copyStatus }}
              </span>
              <button
                @click="copyToClipboard"
                class="px-3 py-1 text-xs bg-blue-600 text-white rounded hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1"
                title="Copy JSON to clipboard"
              >
                📋 Copy
              </button>
              <!-- Share Button (shown when PDF is ready) -->
              <button
                v-if="pdfUrl && !pdfLoading"
                @click="sharePdf"
                class="px-3 py-1 text-xs bg-blue-600 text-white rounded hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1"
                title="Download PDF"
              >
                🔗 Share
              </button>
              
              <!-- PDF Loading indicator -->
              <div
                v-if="pdfLoading"
                class="px-3 py-1 text-xs text-gray-600 flex items-center gap-1"
              >
                <span class="animate-spin">🔄</span>
                PDF...
              </div>
            </div>
          </div>
          <pre class="p-4 bg-gray-50 border rounded-md text-xs overflow-auto max-h-96">{{ JSON.stringify(decodedPayload, null, 2) }}</pre>
        </div>
        
        <!-- PDF Viewer -->
        <div v-if="pdfUrl" class="space-y-2">
          <h4 class="font-semibold">📄 Election Return PDF</h4>
          <div class="border border-gray-300 rounded-md overflow-hidden">
            <iframe
              :src="pdfUrl"
              class="w-full h-96"
              title="Election Return PDF"
            >
              Your browser doesn't support PDF viewing. 
              <a :href="pdfUrl" target="_blank" class="text-blue-600 hover:underline">Download PDF</a>
            </iframe>
          </div>
        </div>
        
        <!-- PDF Error -->
        <div v-if="pdfError" class="p-3 bg-red-50 border border-red-200 rounded-md">
          <p class="text-red-800 text-sm">🚫 PDF Error: {{ pdfError }}</p>
        </div>
      </div>
    </div>
  </div>
</template>