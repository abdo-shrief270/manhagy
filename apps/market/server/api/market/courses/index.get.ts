const ALLOWED = ['q', 'node', 'kind', 'price', 'min', 'max', 'teacher', 'sort', 'page']

export default defineCachedEventHandler(async event => await publicApi(event, '/market/courses', pick(event, ALLOWED)).catch(rethrow), {
  maxAge: 30, swr: true, getKey: event => queryKey('courses', pick(event, ALLOWED)),
})
