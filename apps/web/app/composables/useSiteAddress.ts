/** The account's student platform address (GET /site, shared with the top bar). */
export function useSiteAddress() {
  const store = useSessionStore()
  const { data } = useNuxtData<{ url: string | null } | null>('my-site')
  const fallback = useAsyncData('my-site', () => store.can('site.manage') ? useApi()<{ data: { url: string | null } }>('/site').then(r => r.data) : Promise.resolve(null))
  const url = computed(() => (data.value ?? fallback.data.value)?.url ?? '')
  const host = computed(() => url.value.replace(/^https?:\/\//, ''))
  return { url, host }
}
