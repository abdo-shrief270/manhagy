export default defineCachedEventHandler(async event => await publicApi(event, '/market/nodes', pick(event, ['parent'])).catch(rethrow), {
  maxAge: 300, swr: true, getKey: event => queryKey('nodes', pick(event, ['parent'])),
})
