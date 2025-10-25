<script setup lang="ts">
import { computed, ref } from 'vue'
import { Trash2, Plus } from 'lucide-vue-next'

interface Props {
  fieldKey: string
  value: any
  depth?: number
}

const props = withDefaults(defineProps<Props>(), {
  depth: 0,
})

const emit = defineEmits<{
  'update:value': [value: any]
  'remove': []
}>()

const fieldType = computed(() => {
  if (props.value === null) return 'null'
  if (Array.isArray(props.value)) return 'array'
  return typeof props.value
})

const isExpanded = ref(true)

function updateValue(newValue: any) {
  emit('update:value', newValue)
}

function updatePrimitiveValue(event: Event) {
  const target = event.target as HTMLInputElement
  let value: any = target.value

  // Type coercion based on current type
  if (fieldType.value === 'number') {
    value = parseFloat(value) || 0
  } else if (fieldType.value === 'boolean') {
    // This shouldn't happen with checkbox, but just in case
    value = value === 'true'
  }

  emit('update:value', value)
}

function updateNestedField(key: string | number, value: any) {
  if (Array.isArray(props.value)) {
    const newArray = [...props.value]
    newArray[key as number] = value
    emit('update:value', newArray)
  } else if (typeof props.value === 'object' && props.value !== null) {
    emit('update:value', { ...props.value, [key]: value })
  }
}

function removeNestedField(key: string | number) {
  if (Array.isArray(props.value)) {
    const newArray = props.value.filter((_, i) => i !== key)
    emit('update:value', newArray)
  } else if (typeof props.value === 'object' && props.value !== null) {
    const newObj = { ...props.value }
    delete newObj[key as string]
    emit('update:value', newObj)
  }
}

function addArrayItem() {
  if (Array.isArray(props.value)) {
    emit('update:value', [...props.value, ''])
  }
}

const newObjectKey = ref('')
function addObjectKey() {
  if (newObjectKey.value && typeof props.value === 'object' && !Array.isArray(props.value)) {
    emit('update:value', { ...props.value, [newObjectKey.value]: '' })
    newObjectKey.value = ''
  }
}

function toggleBoolean() {
  emit('update:value', !props.value)
}
</script>

<template>
  <div class="mb-3" :style="{ marginLeft: `${depth * 1.5}rem` }">
    <div class="flex items-start gap-2">
      <div class="flex-1">
        <!-- Field Label -->
        <label class="mb-1.5 block text-sm font-medium text-gray-700">
          {{ fieldKey }}
          <span class="ml-1 text-xs text-gray-500">({{ fieldType }})</span>
        </label>

        <!-- String Input -->
        <input
          v-if="fieldType === 'string'"
          type="text"
          :value="value"
          @input="updatePrimitiveValue"
          class="w-full h-9 px-3 rounded-md border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm"
        />

        <!-- Number Input -->
        <input
          v-else-if="fieldType === 'number'"
          type="number"
          :value="value"
          @input="updatePrimitiveValue"
          class="w-full h-9 px-3 rounded-md border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm"
        />

        <!-- Boolean Checkbox -->
        <div v-else-if="fieldType === 'boolean'" class="flex items-center gap-2">
          <input
            type="checkbox"
            :checked="value"
            @change="toggleBoolean"
            class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
          />
          <span class="text-sm text-gray-600">{{ value ? 'True' : 'False' }}</span>
        </div>

        <!-- Null -->
        <div v-else-if="fieldType === 'null'" class="text-sm text-gray-400 italic mt-1">
          null
        </div>

        <!-- Object -->
        <div v-else-if="fieldType === 'object'" class="mt-1">
          <div class="flex items-center gap-2 mb-2">
            <button
              type="button"
              @click="isExpanded = !isExpanded"
              class="h-6 px-2 text-xs rounded text-gray-600 hover:bg-gray-100 transition-colors"
            >
              {{ isExpanded ? '▼' : '▶' }} Object ({{ Object.keys(value).length }} keys)
            </button>
          </div>
          
          <div v-if="isExpanded" class="border-l-2 border-gray-200 pl-2">
            <DataEditorField
              v-for="(val, key) in value"
              :key="String(key)"
              :field-key="String(key)"
              :value="val"
              :depth="depth + 1"
              @update:value="(newVal) => updateNestedField(key, newVal)"
              @remove="removeNestedField(key)"
            />
            
            <!-- Add new object key -->
            <div class="flex gap-2 mt-2">
              <input
                v-model="newObjectKey"
                placeholder="New key name"
                class="flex-1 h-9 px-3 rounded-md border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                @keyup.enter="addObjectKey"
              />
              <button
                type="button"
                @click="addObjectKey"
                :disabled="!newObjectKey"
                class="h-9 px-3 text-sm rounded-md bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-1"
              >
                <Plus class="h-4 w-4" />
                Add
              </button>
            </div>
          </div>
        </div>

        <!-- Array -->
        <div v-else-if="fieldType === 'array'" class="mt-1">
          <div class="flex items-center gap-2 mb-2">
            <button
              type="button"
              @click="isExpanded = !isExpanded"
              class="h-6 px-2 text-xs rounded text-gray-600 hover:bg-gray-100 transition-colors"
            >
              {{ isExpanded ? '▼' : '▶' }} Array ({{ value.length }} items)
            </button>
          </div>
          
          <div v-if="isExpanded" class="border-l-2 border-gray-200 pl-2">
            <DataEditorField
              v-for="(item, index) in value"
              :key="index"
              :field-key="`[${index}]`"
              :value="item"
              :depth="depth + 1"
              @update:value="(newVal) => updateNestedField(index, newVal)"
              @remove="removeNestedField(index)"
            />
            
            <!-- Add new array item -->
            <button
              type="button"
              @click="addArrayItem"
              class="mt-2 px-3 py-1.5 text-sm rounded-md border border-gray-300 bg-white hover:bg-gray-50 flex items-center gap-1"
            >
              <Plus class="h-4 w-4" />
              Add Item
            </button>
          </div>
        </div>
      </div>

      <!-- Remove Button -->
      <button
        v-if="depth > 0"
        type="button"
        @click="emit('remove')"
        class="h-9 w-9 p-0 rounded text-red-500 hover:text-red-700 hover:bg-red-50 flex items-center justify-center"
        title="Remove field"
      >
        <Trash2 class="h-4 w-4" />
      </button>
    </div>
  </div>
</template>
