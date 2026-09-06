import { getXsrfHeaders, hasXsrfToken } from '~/lib/csrf'
import { isClient } from '~/lib/env'

export interface AuthHandler {
  handleUnauthorized: (
    endpoint: string,
    options: RequestOptions
  ) => Promise<{ retry?: boolean } | void>
}

/** Laravel answers an expired or mismatched session with 419 on stateful writes. */
const CSRF_EXPIRED_STATUS = 419

const CSRF_COOKIE_ENDPOINT = '/auth/csrf-cookie'

/** An HTML error page can be arbitrarily large; keep just enough to identify it. */
const MAX_ERROR_BODY_LENGTH = 500

/**
 * The Echo socket id, so `broadcast(...)->toOthers()` can exclude the client
 * that caused the change. Without it every save self-echoes.
 */
const getSocketId = (): string | null => {
  if (!isClient) return null
  try {
    return (window as { Echo?: { socketId?: () => string | undefined } }).Echo?.socketId?.() ?? null
  } catch {
    return null
  }
}

export interface RequestOptions extends Omit<RequestInit, 'body'> {
  query?: Record<string, unknown>
  body?: unknown
  /**
   * Skip the CSRF machinery: no cookie priming, no X-XSRF-TOKEN, no 419
   * retry. For anonymous endpoints only, and paired with `credentials: 'omit'`.
   */
  skipCsrf?: boolean
}

export interface ApiError extends Error {
  status: number
  data: Record<string, unknown>
  response: Response
}

export interface DownloadedFile {
  blob: Blob
  filename: string
  /** The server capped the row count, so the file is shorter than the list. */
  truncated: boolean
}

/** `attachment; filename="people-2026-09-06.csv"` — quoted or not. */
const filenameFrom = (header: string | null, fallback: string): string => {
  const match = header?.match(/filename\*?=(?:UTF-8''|")?([^";]+)/i)

  return match?.[1] ? decodeURIComponent(match[1].trim()) : fallback
}

/**
 * The one fetch wrapper. Auth, account, invites and the /rq transport all
 * go through here, so CSRF, the 401 handler, Laravel-style query strings and
 * the socket id header apply everywhere.
 */
export class ApiClient {
  private readonly baseURL: string
  private readonly defaultHeaders: Record<string, string>
  private authHandler?: AuthHandler
  private csrfReady = false
  private readonly observers: Array<(response: Response) => void> = []

  constructor(options: { baseURL?: string; defaultHeaders?: Record<string, string> } = {}) {
    this.baseURL = options.baseURL ?? ''
    this.defaultHeaders = {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      ...options.defaultHeaders,
    }
  }

  public setAuthHandler(handler: AuthHandler): void {
    this.authHandler = handler
  }

  /** Sees every response before it is parsed (the version-mismatch check). */
  public observe(observer: (response: Response) => void): void {
    this.observers.push(observer)
  }

  /**
   * Laravel parses `filter[parent_id]=x` into a nested array and
   * `tags[]=a&tags[]=b` into a list; dates travel as ISO strings.
   */
  private appendQueryParam(params: URLSearchParams, key: string, value: unknown): void {
    if (value === undefined || value === null) return

    if (Array.isArray(value)) {
      for (const item of value) this.appendQueryParam(params, `${key}[]`, item)
      return
    }

    if (value instanceof Date) {
      params.append(key, value.toISOString())
      return
    }

    if (typeof value === 'object') {
      for (const [nestedKey, nestedValue] of Object.entries(value as Record<string, unknown>)) {
        this.appendQueryParam(params, `${key}[${nestedKey}]`, nestedValue)
      }
      return
    }

    params.append(key, String(value))
  }

  public resolveUrl(endpoint: string, query?: Record<string, unknown>): string {
    let url = endpoint.startsWith('http') ? endpoint : `${this.baseURL}${endpoint}`

    if (query) {
      const params = new URLSearchParams()
      for (const [key, value] of Object.entries(query)) this.appendQueryParam(params, key, value)
      const serialized = params.toString()
      if (serialized) url += `?${serialized}`
    }

    return url
  }

  public async ensureCsrfCookie(force = false): Promise<void> {
    if (!isClient) return
    if (this.csrfReady && !force) return

    try {
      const response = await fetch(`${this.baseURL}${CSRF_COOKIE_ENDPOINT}`, {
        method: 'GET',
        credentials: 'include',
        headers: { Accept: 'application/json' },
      })

      // A 200 only means the endpoint answered; the cookie is what the next
      // request needs, so stay unprimed until it is really there.
      if (response.ok && hasXsrfToken()) {
        this.csrfReady = true
      }
    } catch {
      // The write will fail with 419 and retry through the same path.
    }
  }

  private async parseResponse<T>(response: Response): Promise<T> {
    for (const observer of this.observers) observer(response)

    const contentType = response.headers.get('content-type') ?? ''
    const isJson = contentType.includes('json')

    if (!response.ok) {
      let data: Record<string, unknown> = {}

      if (isJson) {
        try {
          data = (await response.json()) as Record<string, unknown>
        } catch {
          // A bare status is all there is.
        }
      } else {
        try {
          const body = (await response.text()).trim()
          if (body) data = { message: body.slice(0, MAX_ERROR_BODY_LENGTH) }
        } catch {
          // Same.
        }
      }

      // statusText is empty over HTTP/2, so the status is the fallback.
      const message =
        typeof data.message === 'string' && data.message
          ? data.message
          : response.statusText || `HTTP ${response.status}`
      const error = new Error(message) as ApiError
      error.status = response.status
      error.data = data
      error.response = response
      throw error
    }

    if (response.status === 204) return undefined as T
    if (isJson) return (await response.json()) as T
    return (await response.text()) as unknown as T
  }

  public async request<T>(endpoint: string, options: RequestOptions = {}): Promise<T> {
    const { query, body, skipCsrf, ...fetchOptions } = options
    const method = (fetchOptions.method ?? 'GET').toUpperCase()
    const isFormData = typeof FormData !== 'undefined' && body instanceof FormData
    const isSafeMethod = method === 'GET' || method === 'HEAD' || method === 'OPTIONS'
    const needsCsrf = !isSafeMethod && !skipCsrf

    if (needsCsrf) await this.ensureCsrfCookie()

    const url = this.resolveUrl(endpoint, query)
    const socketId = !isSafeMethod && !skipCsrf ? getSocketId() : null

    const makeRequest = async (requestHeaders: Record<string, string>): Promise<T> => {
      const headers: Record<string, string> = {
        ...(socketId ? { 'X-Socket-ID': socketId } : {}),
        ...requestHeaders,
        ...(fetchOptions.headers as Record<string, string> | undefined),
      }

      if (isFormData) delete headers['Content-Type']

      const response = await fetch(url, {
        ...fetchOptions,
        method,
        credentials: fetchOptions.credentials ?? 'include',
        headers,
        body: body === undefined ? undefined : isFormData ? body : JSON.stringify(body),
      })

      return this.parseResponse<T>(response)
    }

    try {
      return await makeRequest({ ...this.defaultHeaders, ...(needsCsrf ? getXsrfHeaders() : {}) })
    } catch (error) {
      // A 419 usually means the token went stale (long-lived tab, rotated
      // session). Refresh the cookie and retry exactly once.
      if ((error as ApiError).status === CSRF_EXPIRED_STATUS && needsCsrf) {
        try {
          await this.ensureCsrfCookie(true)
          return await makeRequest({ ...this.defaultHeaders, ...getXsrfHeaders() })
        } catch (retryError) {
          return await this.handleAuthError(retryError, endpoint, options, makeRequest)
        }
      }

      return await this.handleAuthError(error, endpoint, options, makeRequest)
    }
  }

  private async handleAuthError<T>(
    error: unknown,
    endpoint: string,
    options: RequestOptions,
    makeRequest: (headers: Record<string, string>) => Promise<T>
  ): Promise<T> {
    const status = (error as ApiError).status

    if ((status === 401 || status === CSRF_EXPIRED_STATUS) && this.authHandler) {
      // The next request re-primes the cookie against a fresh session.
      this.csrfReady = false

      const retryInfo = await this.authHandler.handleUnauthorized(endpoint, options)

      if (retryInfo?.retry) {
        return await makeRequest({ ...this.defaultHeaders, ...getXsrfHeaders() })
      }
    }

    throw error
  }

  /**
   * A file, not a payload. The response is read as a blob so the caller can
   * show a busy state and surface a failure as a normal error, which a plain
   * `<a download>` cannot: a 403 there lands the user on an error page.
   */
  public async download(
    endpoint: string,
    query: Record<string, unknown> = {},
    fallbackName = 'download'
  ): Promise<DownloadedFile> {
    const response = await fetch(this.resolveUrl(endpoint, query), {
      method: 'GET',
      credentials: 'include',
      headers: { Accept: '*/*', 'X-Requested-With': 'XMLHttpRequest' },
    })

    if (!response.ok) {
      // parseResponse throws the ApiError the rest of the app already handles.
      await this.parseResponse<unknown>(response)
    }

    return {
      blob: await response.blob(),
      filename: filenameFrom(response.headers.get('content-disposition'), fallbackName),
      truncated: response.headers.get('x-export-truncated') === '1',
    }
  }

  public get<T>(
    endpoint: string,
    query: Record<string, unknown> = {},
    options: RequestOptions = {}
  ): Promise<T> {
    return this.request<T>(endpoint, { method: 'GET', query, ...options })
  }

  public post<T>(endpoint: string, body?: unknown, options: RequestOptions = {}): Promise<T> {
    return this.request<T>(endpoint, { method: 'POST', body, ...options })
  }

  public put<T>(endpoint: string, body?: unknown, options: RequestOptions = {}): Promise<T> {
    return this.request<T>(endpoint, { method: 'PUT', body, ...options })
  }

  public patch<T>(endpoint: string, body?: unknown, options: RequestOptions = {}): Promise<T> {
    return this.request<T>(endpoint, { method: 'PATCH', body, ...options })
  }

  public delete<T>(endpoint: string, options: RequestOptions = {}): Promise<T> {
    return this.request<T>(endpoint, { method: 'DELETE', ...options })
  }
}
