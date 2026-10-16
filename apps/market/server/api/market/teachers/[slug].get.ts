export default defineCachedEventHandler(async (event) => {
  const slug = (getRouterParam(event, 'slug') ?? '').toLowerCase()
  if (!/^[a-z0-9-]{3,30}$/.test(slug)) throw createError({ statusCode: 404 })
  return await publicApi(event, `/market/teachers/${slug}`).catch(rethrow)
}, { maxAge: 60, swr: true, getKey: event => `teacher:${getRouterParam(event, 'slug')}` })
