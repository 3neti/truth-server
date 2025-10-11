declare module '@lbhurtado/vue-tally-marks' {
  import { DefineComponent } from 'vue'
  
  export interface TallyMarksProps {
    count: number
    highlightColor?: string
    size?: 'small' | 'medium' | 'large' | string
    color?: string
    animated?: boolean
    animationDelay?: number
    showCount?: boolean
    countPosition?: 'before' | 'after' | 'above' | 'below'
    customClass?: string
    ariaLabel?: string
  }
  
  export const TallyMarks: DefineComponent<TallyMarksProps>
  
  export function install(app: any): void
  
  const plugin: {
    install: typeof install
  }
  
  export default plugin
}