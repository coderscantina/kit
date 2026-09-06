/**
 * The filter vocabulary shared by every list. It mirrors what
 * `coderscantina/laravel-filter` reads on the server, so a chip the user
 * builds here is the query parameter the filter class receives:
 * `field=operator:value`, or a bare `field=value` when the operator is `eq`.
 */

export const FILTER_OPERATORS = [
  'eq',
  'neq',
  'like',
  '!like',
  '^like',
  'like$',
  'lt',
  'lte',
  'gt',
  'gte',
  'in',
  '!in',
  'null',
  '!null',
  'empty',
  '!empty',
] as const

export type FilterOperator = (typeof FILTER_OPERATORS)[number]

/** Operators that are the whole filter; there is no value to ask for. */
export const VALUELESS_OPERATORS: readonly FilterOperator[] = ['null', '!null', 'empty', '!empty']

export type FilterFieldType = 'text' | 'select' | 'date' | 'boolean'

export interface FilterOption {
  value: string
  label: string
}

export interface FilterField {
  /** The query parameter, and the filter method's name on the server. */
  id: string
  label: string
  /** Decides the default operators and how the value is picked. Defaults to `text`. */
  type?: FilterFieldType
  /** For `select`: what the value may be. */
  options?: FilterOption[]
  /** Overrides the operators the type would offer. One operator skips the operator step. */
  operators?: readonly FilterOperator[]
}

/**
 * What each field type asks by default. A text field leads with `like`
 * because that is what someone typing a fragment means; a select leads with
 * `eq` because a picked option is an exact answer.
 */
export const DEFAULT_OPERATORS: Record<FilterFieldType, readonly FilterOperator[]> = {
  text: ['like', 'eq', '^like', 'like$', '!like', 'empty', '!empty'],
  select: ['eq', 'neq', 'in', '!in'],
  date: ['gte', 'lte', 'eq'],
  boolean: ['eq'],
}

export const operatorsFor = (field: FilterField): readonly FilterOperator[] =>
  field.operators ?? DEFAULT_OPERATORS[field.type ?? 'text']

const isOperator = (value: string): value is FilterOperator =>
  (FILTER_OPERATORS as readonly string[]).includes(value)

export interface ActiveFilter {
  field: string
  operator: FilterOperator
  value: string
}

/**
 * `eq` is written bare, so `role=admin` stays a readable URL and a saved view
 * stays small. Everything else carries its operator.
 */
export const encodeFilter = (filter: ActiveFilter): string => {
  if (VALUELESS_OPERATORS.includes(filter.operator)) return filter.operator
  if (filter.operator === 'eq') return filter.value

  return `${filter.operator}:${filter.value}`
}

export const decodeFilter = (field: string, raw: string): ActiveFilter => {
  if (isOperator(raw) && VALUELESS_OPERATORS.includes(raw)) {
    return { field, operator: raw, value: '' }
  }

  const separator = raw.indexOf(':')
  const prefix = separator === -1 ? '' : raw.slice(0, separator)

  if (prefix !== '' && isOperator(prefix)) {
    return { field, operator: prefix, value: raw.slice(separator + 1) }
  }

  return { field, operator: 'eq', value: raw }
}

/**
 * The files a list can be downloaded as. The value is the `format` query
 * parameter and the file extension, matching `App\Support\Export\ExportFormat`.
 */
export const EXPORT_FORMATS = ['csv', 'xlsx', 'pdf'] as const

export type ExportFormat = (typeof EXPORT_FORMATS)[number]

/** The icon each format wears in the menu, so the list is scannable. */
export const EXPORT_FORMAT_ICONS: Record<ExportFormat, string> = {
  csv: 'lucide:file-text',
  xlsx: 'lucide:file-spreadsheet',
  pdf: 'lucide:file-type-2',
}

/**
 * One slice of a list, as `TableSegments` shows it. The value is what the
 * caller writes to its filter parameter; the count is optional, because a
 * segment nobody counted is still a segment.
 */
export interface TableSegment<T extends string = string> {
  value: T
  label: string
  count?: number
}
