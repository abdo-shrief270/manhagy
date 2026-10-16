export default defineCachedEventHandler(async event => await publicApi(event, '/market').catch(rethrow), { maxAge: 60, swr: true, getKey: () => 'home' })
