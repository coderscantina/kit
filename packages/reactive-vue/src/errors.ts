/** A 422 from /rq/mutate or /rq/subscribe, with Laravel's field errors. */
export class ValidationError extends Error {
  readonly status = 422

  constructor(
    message: string,
    public readonly errors: Record<string, string[]>
  ) {
    super(message)
    this.name = 'ValidationError'
  }

  first(field: string): string | undefined {
    return this.errors[field]?.[0]
  }
}

/** Raised into the query cache when the server revokes a subscription. */
export class ForbiddenError extends Error {
  readonly status = 403

  readonly code = 'FORBIDDEN'

  constructor(message = 'Subscription revoked') {
    super(message)
    this.name = 'ForbiddenError'
  }
}

/**
 * A 409 from /rq/mutate: the row moved between the client's read and its
 * write, and nothing was written. `current` is the row as it is now, in
 * the mutation's result shape, so the caller can resolve field by field
 * (`useReactiveForm().apply(error.current)`) and save again against
 * `current.version`.
 */
export class ConflictError<TCurrent = unknown> extends Error {
  readonly status = 409

  readonly code = 'CONFLICT'

  constructor(
    message: string,
    public readonly expected: number,
    public readonly actual: number,
    public readonly current: TCurrent
  ) {
    super(message)
    this.name = 'ConflictError'
  }
}

interface HttpErrorLike {
  status?: number
  message?: string
  data?: {
    message?: string
    errors?: Record<string, string[]>
    code?: string
    expected?: number
    actual?: number
    current?: unknown
  }
}

/** Map the API client's error shape onto the package's typed errors. */
export const normalizeError = (error: unknown): Error => {
  const http = error as HttpErrorLike | null | undefined

  if (http?.status === 422) {
    return new ValidationError(
      http.data?.message ?? http.message ?? 'Validation failed',
      http.data?.errors ?? {}
    )
  }

  if (http?.status === 409 && http.data?.code === 'CONFLICT') {
    return new ConflictError(
      http.data.message ?? 'The row changed since it was read',
      http.data.expected ?? 0,
      http.data.actual ?? 0,
      http.data.current
    )
  }

  return error instanceof Error ? error : new Error(String(error))
}
