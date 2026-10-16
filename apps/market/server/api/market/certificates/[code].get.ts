export default defineCachedEventHandler(async (event) => {
  const code = (getRouterParam(event, 'code') ?? '').toUpperCase().replace(/[^A-Z0-9]/g, '')
  if (code.length !== 10) throw createError({ statusCode: 404 })
  return await publicApi(event, `/certificates/${code}`).catch(rethrow)
}, { maxAge: 300, swr: true, getKey: event => `cert:${getRouterParam(event, 'code')}` })
