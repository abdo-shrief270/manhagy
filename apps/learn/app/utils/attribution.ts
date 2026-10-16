/**
 * Where a student found a course: a link from «سوق منهجي» carries `?via=market`, remembered on
 * this device for 30 days (through sign-up and topping up the wallet) and sent with the purchase.
 */
const DAYS = 30

function key(slug: string, courseId: string) {
  return `manhagy:via:${slug}:${courseId}`
}

export function rememberVia(slug: string, courseId: string, via: string) {
  if (via !== 'market') return
  try {
    localStorage.setItem(key(slug, courseId), JSON.stringify({ via, at: Date.now() }))
  }
  catch { /* private mode */ }
}

export function viaFor(slug: string, courseId: string): string | undefined {
  try {
    const saved = JSON.parse(localStorage.getItem(key(slug, courseId)) ?? 'null') as { via: string, at: number } | null
    return saved && Date.now() - saved.at < DAYS * 86_400_000 ? saved.via : undefined
  }
  catch {
    return undefined
  }
}
