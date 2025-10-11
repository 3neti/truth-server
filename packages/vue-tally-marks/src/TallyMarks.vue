<script setup lang="ts">
import { computed, toRefs } from 'vue'

export interface TallyMarksProps {
  /** The number to display as tally marks */
  count: number
  /** Color for highlighting the last mark or group */
  highlightColor?: string
  /** Size of the tally marks (small, medium, large, or custom px value) */
  size?: 'small' | 'medium' | 'large' | string
  /** Color of the tally marks */
  color?: string
  /** Whether to animate the tally marks appearing */
  animated?: boolean
  /** Animation delay between each mark (in ms) */
  animationDelay?: number
  /** Whether to show the numeric count alongside tally marks */
  showCount?: boolean
  /** Position of the count display */
  countPosition?: 'before' | 'after' | 'above' | 'below'
  /** Custom CSS class for styling */
  customClass?: string
  /** Accessibility label for screen readers */
  ariaLabel?: string
}

const props = withDefaults(defineProps<TallyMarksProps>(), {
  highlightColor: '#e74c3c',
  size: 'medium',
  color: 'currentColor',
  animated: false,
  animationDelay: 100,
  showCount: false,
  countPosition: 'after',
  customClass: '',
  ariaLabel: ''
})

const { count, size, color, animated, animationDelay } = toRefs(props)

const fullGroups = computed(() => Math.floor(props.count / 5))
const remainingMarks = computed(() => props.count % 5)
const hasRemainder = computed(() => remainingMarks.value > 0)

const isLastGroup = (index: number): boolean => {
  return index === fullGroups.value - 1
}

const sizeStyles = computed(() => {
  const sizeMap = {
    small: { fontSize: '0.9rem', gap: '4px', groupGap: '1px', diagonalWidth: '42px' },
    medium: { fontSize: '1.2rem', gap: '6px', groupGap: '2px', diagonalWidth: '52px' },
    large: { fontSize: '1.5rem', gap: '8px', groupGap: '3px', diagonalWidth: '62px' }
  }
  
  if (props.size in sizeMap) {
    return sizeMap[props.size as keyof typeof sizeMap]
  }
  
  // Custom size (assuming it's a px value like "16px")
  const customSize = props.size
  const numericSize = parseInt(customSize) || 16
  // Calculate diagonal width based on original ratio: 52px for 1.2rem (19.2px) = ~2.7x
  const diagonalWidth = Math.round(numericSize * 2.7)
  return {
    fontSize: customSize,
    gap: `${Math.max(4, numericSize * 0.3)}px`,
    groupGap: `${Math.max(1, numericSize * 0.1)}px`,
    diagonalWidth: `${diagonalWidth}px`
  }
})

const computedAriaLabel = computed(() => {
  if (props.ariaLabel) return props.ariaLabel
  return `Tally marks showing count of ${props.count}`
})

const getAnimationDelay = (groupIndex: number, markIndex?: number): string => {
  if (!props.animated) return '0s'
  
  const baseDelay = groupIndex * 5 * props.animationDelay
  const markDelay = markIndex !== undefined ? markIndex * props.animationDelay : 0
  return `${(baseDelay + markDelay) / 1000}s`
}
</script>

<template>
  <div 
    :class="['tally-marks-container', customClass]"
    :aria-label="computedAriaLabel"
    role="img"
  >
    <!-- Count display above -->
    <div v-if="showCount && countPosition === 'above'" class="tally-count tally-count--above">
      {{ count }}
    </div>
    
    <div class="tally-marks-wrapper">
      <!-- Count display before -->
      <span v-if="showCount && countPosition === 'before'" class="tally-count tally-count--before">
        {{ count }}
      </span>
      
      <div 
        class="tally-marks"
        :style="{
          gap: sizeStyles.gap,
          color: color
        }"
      >
        <!-- Render full groups of 5 -->
        <div
          v-for="(group, index) in fullGroups"
          :key="'group-' + index"
          class="tally-group"
          :style="{
            gap: sizeStyles.groupGap,
            animationDelay: getAnimationDelay(index)
          }"
          :class="{ 'tally-group--animated': animated }"
        >
          <span 
            v-for="markIndex in 4"
            :key="'mark-' + markIndex"
            class="mark"
            :style="{
              fontSize: sizeStyles.fontSize,
              animationDelay: getAnimationDelay(index, markIndex - 1)
            }"
            :class="{ 'mark--animated': animated }"
          >|</span>
          <span
            class="diagonal"
            :style="{ 
              backgroundColor: isLastGroup(index) && !hasRemainder ? highlightColor : 'black',
              width: sizeStyles.diagonalWidth,
              animationDelay: getAnimationDelay(index, 4)
            }"
            :class="{ 'diagonal--animated': animated }"
          />
        </div>

        <!-- Render leftover marks (less than 5) -->
        <div 
          v-if="remainingMarks > 0" 
          class="tally-group"
          :style="{
            gap: sizeStyles.groupGap,
            animationDelay: getAnimationDelay(fullGroups)
          }"
          :class="{ 'tally-group--animated': animated }"
        >
          <span
            v-for="mark in remainingMarks"
            :key="'mark-' + mark"
            class="mark"
            :style="{
              fontSize: sizeStyles.fontSize,
              color: mark === remainingMarks ? highlightColor : color,
              animationDelay: getAnimationDelay(fullGroups, mark - 1)
            }"
            :class="{ 'mark--animated': animated }"
          >|</span>
        </div>
      </div>
      
      <!-- Count display after -->
      <span v-if="showCount && countPosition === 'after'" class="tally-count tally-count--after">
        {{ count }}
      </span>
    </div>
    
    <!-- Count display below -->
    <div v-if="showCount && countPosition === 'below'" class="tally-count tally-count--below">
      {{ count }}
    </div>
  </div>
</template>

<style scoped>
.tally-marks-container {
  display: inline-block;
}

.tally-marks-wrapper {
  display: flex;
  align-items: center;
  gap: 8px;
}

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
  user-select: none;
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

.tally-count {
  font-weight: bold;
  font-family: system-ui, -apple-system, sans-serif;
}

.tally-count--above,
.tally-count--below {
  text-align: center;
  margin: 4px 0;
}

.tally-count--before {
  margin-right: 8px;
}

.tally-count--after {
  margin-left: 8px;
}

/* Animation styles */
.tally-group--animated {
  opacity: 0;
  animation: fadeInUp 0.3s ease-out forwards;
}

.mark--animated {
  opacity: 0;
  transform: translateY(10px);
  animation: fadeInUp 0.2s ease-out forwards;
}

.diagonal--animated {
  opacity: 0;
  transform: translateY(10px) rotate(21deg);
  animation: fadeInDiagonal 0.3s ease-out forwards;
}

@keyframes fadeInUp {
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes fadeInDiagonal {
  to {
    opacity: 1;
    transform: translateY(0) rotate(21deg);
  }
}

/* Responsive design */
@media (max-width: 768px) {
  .tally-marks {
    gap: 4px;
  }
  
  .tally-group {
    gap: 1px;
  }
}

/* High contrast mode support */
@media (prefers-contrast: high) {
  .mark {
    font-weight: 900;
  }
  
  .diagonal {
    height: 3px;
  }
}

/* Reduced motion support */
@media (prefers-reduced-motion: reduce) {
  .tally-group--animated,
  .mark--animated,
  .diagonal--animated {
    animation: none;
    opacity: 1;
    transform: none;
  }
}
</style>