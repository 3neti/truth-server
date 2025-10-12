<script setup lang="ts">
import { ref } from 'vue'
import axios from 'axios'

// State
const chunks = ref('')
const loading = ref(false)
const error = ref('')
const result = ref<any>(null)
const decodedPayload = ref<any>(null)
const copyStatus = ref('')

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

    <!-- INPUT SECTION -->
    <div class="space-y-4">
      <div>
        <label for="chunks" class="block text-sm font-medium text-gray-700 mb-2">
          Encoded Chunks (one per line):
        </label>
        <textarea
          id="chunks"
          v-model="chunks"
          rows="8"
          class="w-full p-3 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 font-mono text-xs"
          placeholder="Paste your encoded chunks here, one per line...&#10;Example:&#10;ER|v1|317537|1/6|7Z1bc-LIkoD_SoVf...&#10;ER|v1|317537|2/6|xh-tl35MFCZ2UZjY..."
        ></textarea>
      </div>

      <!-- CONTROLS -->
      <div class="flex gap-3">
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
            </div>
          </div>
          <pre class="p-4 bg-gray-50 border rounded-md text-xs overflow-auto max-h-96">{{ JSON.stringify(decodedPayload, null, 2) }}</pre>
        </div>
      </div>
    </div>
  </div>
</template>