export type SchemaFieldType = 
  | 'string' 
  | 'number' 
  | 'integer' 
  | 'boolean' 
  | 'array' 
  | 'object'

export type SchemaFormat = 
  | 'email' 
  | 'uri' 
  | 'date' 
  | 'date-time' 
  | 'time'
  | 'color'
  | 'tel'

export interface SchemaField {
  key: string
  type: SchemaFieldType
  title?: string
  description?: string
  format?: SchemaFormat
  
  // Validation
  required?: boolean
  minLength?: number
  maxLength?: number
  minimum?: number
  maximum?: number
  pattern?: string
  enum?: string[]
  
  // Defaults
  default?: any
  
  // Array-specific
  items?: SchemaField
  minItems?: number
  maxItems?: number
  
  // Object-specific
  properties?: Record<string, SchemaField>
  
  // UI hints
  placeholder?: string
  helpText?: string
  widget?: 'textarea' | 'select' | 'radio' | 'checkbox' | 'slider' | 'color-picker'
}

export interface JSONSchema {
  $schema?: string
  type: 'object'
  title?: string
  description?: string
  properties: Record<string, SchemaField>
  required?: string[]
  additionalProperties?: boolean
}

export function fieldToJsonSchema(field: SchemaField): any {
  const schema: any = {
    type: field.type,
  }
  
  if (field.title) schema.title = field.title
  if (field.description) schema.description = field.description
  if (field.format) schema.format = field.format
  if (field.default !== undefined) schema.default = field.default
  
  // Validation
  if (field.minLength !== undefined) schema.minLength = field.minLength
  if (field.maxLength !== undefined) schema.maxLength = field.maxLength
  if (field.minimum !== undefined) schema.minimum = field.minimum
  if (field.maximum !== undefined) schema.maximum = field.maximum
  if (field.pattern) schema.pattern = field.pattern
  if (field.enum) schema.enum = field.enum
  
  // Array
  if (field.type === 'array') {
    if (field.items) schema.items = fieldToJsonSchema(field.items)
    if (field.minItems !== undefined) schema.minItems = field.minItems
    if (field.maxItems !== undefined) schema.maxItems = field.maxItems
  }
  
  // Object
  if (field.type === 'object' && field.properties) {
    schema.properties = {}
    for (const [key, prop] of Object.entries(field.properties)) {
      schema.properties[key] = fieldToJsonSchema(prop)
    }
  }
  
  return schema
}

export function jsonSchemaToFields(schema: any): Record<string, SchemaField> {
  const fields: Record<string, SchemaField> = {}
  
  if (!schema.properties) return fields
  
  for (const [key, prop] of Object.entries(schema.properties as Record<string, any>)) {
    fields[key] = {
      key,
      type: prop.type || 'string',
      title: prop.title,
      description: prop.description,
      format: prop.format,
      default: prop.default,
      
      // Validation
      required: schema.required?.includes(key),
      minLength: prop.minLength,
      maxLength: prop.maxLength,
      minimum: prop.minimum,
      maximum: prop.maximum,
      pattern: prop.pattern,
      enum: prop.enum,
      
      // Array
      items: prop.items,
      minItems: prop.minItems,
      maxItems: prop.maxItems,
      
      // Object
      properties: prop.properties,
    }
  }
  
  return fields
}

export function fieldsToJsonSchema(fields: Record<string, SchemaField>): JSONSchema {
  const schema: JSONSchema = {
    type: 'object',
    properties: {},
    required: [],
  }
  
  for (const [key, field] of Object.entries(fields)) {
    schema.properties[key] = fieldToJsonSchema(field)
    if (field.required) {
      schema.required!.push(key)
    }
  }
  
  if (schema.required!.length === 0) {
    delete schema.required
  }
  
  return schema
}
