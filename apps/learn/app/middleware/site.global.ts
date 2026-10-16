/** Every page needs the platform (by host); pages marked `auth` need a signed-in student, `guest` the opposite. */
export default defineNuxtRouteMiddleware(async (to) => {
  if (to.meta.parent) return // the parent's report page needs no platform lookup
  const { load, missing } = useSite()
  await load()
  if (missing.value) {
    return to.path === '/missing' ? undefined : navigateTo('/missing')
  }
  const learner = useLearner()
  if (learner.token.value && !learner.me.value) {
    await learner.load().catch(() => learner.setToken(null))
  }
  if (to.meta.auth && !learner.token.value) {
    return navigateTo({ path: '/login', query: { redirect: to.fullPath } })
  }
  if (to.meta.guest && learner.token.value) {
    return navigateTo('/')
  }
})
