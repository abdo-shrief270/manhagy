/** This site's own data handlers (they call the API with the visitor's IP and cache). */
export function marketFetch<T>(path: string, query?: Record<string, unknown>): Promise<T> {
  return $fetch(`/api/market${path}` as string, { query }) as Promise<T>
}
