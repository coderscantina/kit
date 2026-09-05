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

interface HttpErrorLike {
  status?: number
  message?: string
  data?: { message?: string; errors?: Record<string, string[]> }
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

  return error instanceof Error ? error : new Error(String(error))
}
