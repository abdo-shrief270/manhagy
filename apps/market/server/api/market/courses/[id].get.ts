export default defineCachedEventHandler(async (event) => {
  const id = getRouterParam(event, 'id') ?? ''
  if (!UUID.test(id)) throw createError({ statusCode: 404 })
  return await publicApi(event, `/market/courses/${id}`).catch(rethrow)
}, { maxAge: 60, swr: true, getKey: event => `course:${getRouterParam(event, 'id')}` })
