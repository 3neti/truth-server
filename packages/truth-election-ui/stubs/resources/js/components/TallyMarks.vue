<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
    count: number
    highlightColor?: string
}>()

const fullGroups = computed(() => Math.floor(props.count / 5))
const remainingMarks = computed(() => props.count % 5)
const hasRemainder = computed(() => remainingMarks.value > 0)

const isLastGroup = (index: number): boolean => {
    return index === fullGroups.value - 1
}
</script>

<template>
    <div class="tally-marks">
        <!-- Render full groups of 5 -->
        <div
            v-for="(group, index) in fullGroups"
            :key="'group-' + index"
            class="tally-group"
        >
            <span class="mark">|</span>
            <span class="mark">|</span>
            <span class="mark">|</span>
            <span class="mark">|</span>
            <span
                class="diagonal"
                :style="{ backgroundColor: isLastGroup(index) && !hasRemainder ? highlightColor : 'black' }"
            />
        </div>

        <!-- Render leftover marks (less than 5) -->
        <div v-if="remainingMarks > 0" class="tally-group">
      <span
          v-for="mark in remainingMarks"
          :key="'mark-' + mark"
          class="mark"
          :style="{
          color: mark === remainingMarks ? highlightColor : 'black'
        }"
      >|</span>
        </div>
    </div>
</template>

<style scoped>
.tally-marks {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.tally-group {
    position: relative;
    display: flex;
    align-items: center;
    gap: 2px;
}

.mark {
    font-size: 1.2rem;
    font-weight: bold;
    font-family: monospace;
    line-height: 1;
}

.diagonal {
    position: absolute;
    top: 1px;
    left: 1px;
    width: 52px;
    height: 2px;
    background-color: black;
    transform: rotate(21deg);
    transform-origin: left center;
    pointer-events: none;
}
</style>
