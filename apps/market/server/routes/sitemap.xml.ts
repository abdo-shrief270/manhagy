interface Page { data: { id: string, teacher: { slug: string } }[], meta: { last_page: number } }

// The home page, every listed course and every teacher (cached 10 minutes).
export default defineCachedEventHandler(async (event) => {
  const base = String(useRuntimeConfig(event).public.marketUrl).replace(/\/$/, '')
  const courses: Page['data'] = []
  for (let page = 1; page <= 20; page++) {
    const result = await publicApi<Page>(event, '/market/courses', { sort: 'new', page }).catch(() => null)
    if (!result) break
    courses.push(...result.data)
    if (page >= result.meta.last_page) break
  }
  const urls = ['/', '/search', ...courses.map(c => `/courses/${c.id}`), ...[...new Set(courses.map(c => c.teacher.slug))].map(s => `/t/${s}`)]
  setHeader(event, 'Content-Type', 'application/xml; charset=utf-8')
  return `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n${urls.map(u => `  <url><loc>${base}${u}</loc></url>`).join('\n')}\n</urlset>\n`
}, { maxAge: 600, swr: true, getKey: () => 'sitemap' })
