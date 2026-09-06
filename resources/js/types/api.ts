/** The counters Laravel's paginator reports, without the rows. */
export interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

/** Laravel's paginator envelope, as `Data::collect($paginator)` returns it. */
export interface Paginated<T> extends PaginationMeta {
  data: T[]
}

export interface HttpError extends Error {
  status?: number
  data?: { message?: string; error_code?: string; errors?: Record<string, string[]> }
}

export const isHttpError = (error: unknown): error is HttpError =>
  error instanceof Error && 'status' in error

export const errorCode = (error: unknown): string | undefined =>
  isHttpError(error) ? error.data?.error_code : undefined
