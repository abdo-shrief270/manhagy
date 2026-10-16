/** $fetch bound to the API with the student's token. A 401 signs out; a 403 phone_unverified sends to /verify. */
export function useLearnApi() {
  const config = useRuntimeConfig().public
  const token = useState<string | null>('learner-token')

  return $fetch.create({
    baseURL: String(config.apiBase),
    headers: { Accept: 'application/json' },
    onRequest({ options }) {
      if (token.value) options.headers.set('Authorization', `Bearer ${token.value}`)
    },
    async onResponseError({ response }) {
      const code = (response._data as { code?: string } | undefined)?.code
      if (response.status === 401 && token.value) {
        useLearner().setToken(null)
        await navigateTo('/login')
      }
      else if (response.status === 403 && code === 'phone_unverified') {
        await navigateTo('/verify')
      }
    },
  })
}

export function apiMessage(error: unknown): string {
  const data = (error as { data?: { message?: string, errors?: Record<string, string[]> } })?.data
  if (data?.errors) return Object.values(data.errors).flat()[0] ?? data.message ?? 'حصلت مشكلة'
  return data?.message ?? 'حصلت مشكلة، جرّب تاني.'
}

export function apiCode(error: unknown): string | null {
  return (error as { data?: { code?: string } })?.data?.code ?? null
}
