# Vue 3 TallyMarks Component

A beautiful, customizable Vue 3 component for rendering traditional tally marks with diagonal crossing lines. Perfect for displaying counts, votes, survey results, and any numerical data in a visually appealing way.

## ✨ Features

- 🎯 **Traditional tally marks** with diagonal crossing lines (groups of 5)
- 🎨 **Highly customizable** with colors, sizes, and positioning
- 🌈 **Animation support** with configurable delays
- ♿ **Accessibility friendly** with ARIA labels and screen reader support
- 📱 **Responsive design** that works on all devices
- 🎪 **TypeScript support** with full type definitions
- 🚀 **Lightweight** with no external dependencies
- 🌙 **Dark mode compatible** with `currentColor` support

## 📦 Installation

```bash
npm install @lbhurtado/vue-tally-marks
```

```bash
yarn add @lbhurtado/vue-tally-marks
```

```bash
pnpm add @lbhurtado/vue-tally-marks
```

## 🚀 Usage

### Basic Usage

```vue
<template>
  <div>
    <!-- Simple tally marks -->
    <TallyMarks :count="7" />
    
    <!-- With custom colors -->
    <TallyMarks 
      :count="12" 
      color="#2c3e50"
      highlight-color="#e74c3c" 
    />
    
    <!-- With count display -->
    <TallyMarks 
      :count="23" 
      :show-count="true"
      count-position="after"
    />
  </div>
</template>

<script setup>
import { TallyMarks } from '@lbhurtado/vue-tally-marks'
</script>
```

### Global Registration

```javascript
// main.js
import { createApp } from 'vue'
import VueTallyMarks from '@lbhurtado/vue-tally-marks'
import App from './App.vue'

const app = createApp(App)
app.use(VueTallyMarks)
app.mount('#app')
```

After global registration, use it anywhere without importing:

```vue
<template>
  <TallyMarks :count="15" />
</template>
```

## 🎛️ API Reference

### Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `count` | `number` | **Required** | The number to display as tally marks |
| `highlightColor` | `string` | `'#e74c3c'` | Color for highlighting the last mark or group |
| `size` | `'small' \| 'medium' \| 'large' \| string` | `'medium'` | Size of the tally marks or custom px value |
| `color` | `string` | `'currentColor'` | Color of the tally marks |
| `animated` | `boolean` | `false` | Whether to animate the tally marks appearing |
| `animationDelay` | `number` | `100` | Animation delay between each mark (in ms) |
| `showCount` | `boolean` | `false` | Whether to show the numeric count alongside tally marks |
| `countPosition` | `'before' \| 'after' \| 'above' \| 'below'` | `'after'` | Position of the count display |
| `customClass` | `string` | `''` | Custom CSS class for styling |
| `ariaLabel` | `string` | `''` | Accessibility label for screen readers |

### Examples

#### Different Sizes

```vue
<template>
  <div>
    <TallyMarks :count="7" size="small" />
    <TallyMarks :count="7" size="medium" />
    <TallyMarks :count="7" size="large" />
    <TallyMarks :count="7" size="2rem" />
  </div>
</template>
```

#### With Animation

```vue
<template>
  <TallyMarks 
    :count="15" 
    :animated="true"
    :animation-delay="150"
    highlight-color="#3498db"
  />
</template>
```

#### Survey Results Display

```vue
<template>
  <div class="survey-results">
    <div class="result-item">
      <span class="option">JavaScript:</span>
      <TallyMarks 
        :count="19"
        :show-count="true"
        count-position="after"
        highlight-color="#f1c40f"
      />
    </div>
    
    <div class="result-item">
      <span class="option">Python:</span>
      <TallyMarks 
        :count="23"
        :show-count="true"
        count-position="after"
        highlight-color="#3498db"
      />
    </div>
    
    <div class="result-item">
      <span class="option">TypeScript:</span>
      <TallyMarks 
        :count="12"
        :show-count="true"
        count-position="after"
        highlight-color="#2980b9"
      />
    </div>
  </div>
</template>

<style scoped>
.survey-results {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.result-item {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.option {
  font-weight: bold;
  min-width: 120px;
}
</style>
```

#### Interactive Counter

```vue
<template>
  <div class="counter-demo">
    <h3>Vote Counter</h3>
    <div class="controls">
      <button @click="increment" :disabled="count >= 50">+1</button>
      <button @click="decrement" :disabled="count <= 0">-1</button>
      <button @click="reset">Reset</button>
    </div>
    
    <div class="tally-display">
      <TallyMarks 
        :count="count"
        :show-count="true"
        count-position="below"
        :animated="true"
        :animation-delay="50"
        highlight-color="#e74c3c"
        size="large"
      />
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { TallyMarks } from '@lbhurtado/vue-tally-marks'

const count = ref(0)

const increment = () => count.value++
const decrement = () => count.value--
const reset = () => count.value = 0
</script>

<style scoped>
.counter-demo {
  text-align: center;
  padding: 2rem;
}

.controls {
  margin: 1rem 0;
  display: flex;
  gap: 0.5rem;
  justify-content: center;
}

.controls button {
  padding: 0.5rem 1rem;
  border: 2px solid #3498db;
  background: white;
  color: #3498db;
  border-radius: 4px;
  cursor: pointer;
  font-weight: bold;
}

.controls button:hover:not(:disabled) {
  background: #3498db;
  color: white;
}

.controls button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.tally-display {
  margin-top: 2rem;
  padding: 2rem;
  background: #f8f9fa;
  border-radius: 8px;
  border: 2px solid #e9ecef;
}
</style>
```

## 🎨 Styling

The component uses CSS custom properties and is designed to inherit from parent styles. You can customize it further:

```vue
<template>
  <TallyMarks 
    :count="15"
    custom-class="my-custom-tally"
  />
</template>

<style>
.my-custom-tally .mark {
  color: #2c3e50;
  font-size: 1.5rem;
}

.my-custom-tally .diagonal {
  background-color: #e74c3c;
  height: 3px;
}
</style>
```

## ♿ Accessibility

The component includes built-in accessibility features:

- **ARIA labels** for screen readers
- **Semantic HTML** with proper roles
- **High contrast mode** support
- **Reduced motion** support for users with vestibular disorders
- **Keyboard navigation** friendly

```vue
<template>
  <TallyMarks 
    :count="12"
    aria-label="Survey responses: 12 votes for option A"
  />
</template>
```

## 🌍 Browser Support

- ✅ Chrome (latest)
- ✅ Firefox (latest)
- ✅ Safari (latest)
- ✅ Edge (latest)
- ✅ iOS Safari
- ✅ Chrome Mobile

## 🏗️ Development

```bash
# Clone the repository
git clone https://github.com/rli/vue-tally-marks.git

# Install dependencies
npm install

# Build the library
npm run build

# Type checking
npm run type-check
```

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## 📄 License

MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- Inspired by the traditional tally counting system
- Built with Vue 3 Composition API
- TypeScript support for better developer experience

---

Made with ❤️ by [rli](https://github.com/rli)