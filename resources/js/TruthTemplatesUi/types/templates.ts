// Template Document Specification
export interface DocumentSpec {
  title: string
  unique_id: string
  layout?: string
  locale?: string
}

// Template Choice Specification
export interface ChoiceSpec {
  code: string
  label: string
}

// Template Section Specification
export interface SectionSpec {
  type: string
  code: string
  title: string
  layout?: string
  maxSelections?: number
  question?: string
  scale?: number[]
  choices?: ChoiceSpec[]
}

// Complete Template Specification
export interface TemplateSpec {
  document: DocumentSpec
  sections: SectionSpec[]
}

// Template modes
export type TemplateMode = 'simple' | 'advanced'

// Template metadata
export interface Template {
  id?: number
  name: string
  description?: string
  category: string
  handlebars_template?: string
  sample_data?: Record<string, any>
  is_public?: boolean
  storage_type?: 'local' | 'remote' | 'hybrid'
  template_uri?: string
  template_ref?: string
  family_id?: number
  family?: TemplateFamily
  family_slug?: string
  layout_variant?: string
  version?: string
  created_at?: string
  updated_at?: string
}

// Template Family
export interface TemplateFamily {
  id?: number
  slug: string
  name: string
  description?: string
  category: string
  version?: string
  repo_url?: string
  repo_provider?: string
  storage_type?: 'local' | 'remote' | 'hybrid'
  is_public?: boolean
  user_id?: number
  templates?: Template[]
  variants_count?: number
  layout_variants?: string[]
  created_at?: string
  updated_at?: string
}

// Template Data
export interface TemplateData {
  id?: number
  name: string
  description?: string
  template_ref?: string
  category?: string
  data: Record<string, any>
  is_public?: boolean
  user_id?: number
  created_at?: string
  updated_at?: string
}
