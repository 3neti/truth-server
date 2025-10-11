import { App } from 'vue'
import TallyMarks from './TallyMarks.vue'
import type { TallyMarksProps } from './TallyMarks.vue'

// Export the component
export { default as TallyMarks } from './TallyMarks.vue'
export type { TallyMarksProps }

// Plugin install function for global registration
export function install(app: App) {
  app.component('TallyMarks', TallyMarks)
}

// Default export for plugin usage
export default {
  install
}

// Auto-install when used via CDN or when Vue is available globally
if (typeof window !== 'undefined' && (window as any).Vue) {
  (window as any).Vue.use({ install })
}