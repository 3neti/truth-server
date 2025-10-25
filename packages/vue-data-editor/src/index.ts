import { App } from 'vue'
import DataEditor from './DataEditor.vue'
import DataEditorField from './DataEditorField.vue'

// Export the components
export { default as DataEditor } from './DataEditor.vue'
export { default as DataEditorField } from './DataEditorField.vue'

// Plugin install function for global registration
export function install(app: App) {
  app.component('DataEditor', DataEditor)
  app.component('DataEditorField', DataEditorField)
}

// Default export for plugin usage
export default {
  install
}

// Auto-install when used via CDN or when Vue is available globally
if (typeof window !== 'undefined' && (window as any).Vue) {
  (window as any).Vue.use({ install })
}
