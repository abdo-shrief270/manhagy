import type { H3Event } from 'h3'

/**
 * The API's public endpoints, called from this server only (the browser asks /api/market/* here).
 * The visitor's IP goes along, so the API's rate limit is per visitor.
 */
export function publicApi<T>(event: H3Event, path: string, query?: Record<string, unknown>): Promise<T> {
  const config = useRuntimeConfig(event)
  const ip = getRequestIP(event, { xForwardedFor: true })
  return $fetch(`${config.apiInternal}/public${path}` as string, {
    query,
    headers: { Accept: 'application/json', ...(ip ? { 'X-Forwarded-For': ip } : {}) },
    timeout: 8000,
  }) as Promise<T>
}

/** API errors keep their meaning: 404 = no such course / teacher / certificate. */
export function rethrow(error: unknown): never {
  const status = (error as { statusCode?: number, status?: number }).statusCode ?? (error as { status?: number }).status ?? 502
  const kept = [404, 422].includes(status) ? status : 502
  throw createError({ statusCode: kept, statusMessage: kept === 404 ? 'Not found' : 'Market unavailable' })
}

/** Only the query keys an endpoint takes, without empty ones. */
export function pick(event: H3Event, allowed: string[]): Record<string, unknown> {
  return Object.fromEntries(Object.entries(getQuery(event)).filter(([k, v]) => allowed.includes(k) && v !== '' && v !== undefined))
}

export function queryKey(prefix: string, query: Record<string, unknown>): string {
  return `${prefix}:${new URLSearchParams(query as Record<string, string>).toString()}`
}

export const UUID = /^[0-9a-f-]{36}$/i
