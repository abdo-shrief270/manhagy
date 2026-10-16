import type { Me, Verification } from '~/utils/types'

/** The device's own key (a student's token is tied to a device; the teacher limits how many). */
export function deviceKey(): string {
  let key = localStorage.getItem('manhagy:device-key')
  if (!key) {
    key = Array.from(crypto.getRandomValues(new Uint8Array(24)), b => b.toString(16).padStart(2, '0')).join('')
    localStorage.setItem('manhagy:device-key', key)
  }
  return key
}

export function deviceName(): string {
  const ua = navigator.userAgent
  const os = /iPhone|iPad/.test(ua) ? 'iPhone' : /Android/.test(ua) ? 'Android' : /Windows/.test(ua) ? 'Windows' : /Mac/.test(ua) ? 'Mac' : 'جهاز'
  const browser = /Edg\//.test(ua) ? 'Edge' : /Chrome\//.test(ua) ? 'Chrome' : /Safari\//.test(ua) ? 'Safari' : /Firefox\//.test(ua) ? 'Firefox' : 'متصفح'
  return `${browser} · ${os}`
}

/** The signed-in student on this platform (token per platform, in localStorage). */
export function useLearner() {
  const { slug } = useSite()
  const key = computed(() => `manhagy:learner-token:${slug.value}`)
  const token = useState<string | null>('learner-token', () => localStorage.getItem(key.value))
  const me = useState<Me | null>('learner-me', () => null)
  const pendingVerification = useState<Verification | null>('learner-verification', () => null)

  function setToken(value: string | null) {
    token.value = value
    value ? localStorage.setItem(key.value, value) : localStorage.removeItem(key.value)
  }

  async function load(force = false) {
    if (!token.value) return null
    if (me.value && !force) return me.value
    me.value = (await useLearnApi()<{ data: Me }>('/learner/me')).data
    return me.value
  }

  async function login(phone: string, password: string) {
    const res = await useLearnApi()<{ token: string }>(`/public/sites/${slug.value}/login`, { method: 'POST', body: { phone, password, device_key: deviceKey(), device_name: deviceName() } })
    setToken(res.token)
    await load(true)
  }

  async function register(payload: Record<string, unknown>) {
    const res = await useLearnApi()<{ token: string, verification: Verification | null }>(`/public/sites/${slug.value}/register`, { method: 'POST', body: { ...payload, device_key: deviceKey(), device_name: deviceName() } })
    setToken(res.token)
    pendingVerification.value = res.verification
    await load(true)
  }

  async function logout() {
    await useLearnApi()('/learner/logout', { method: 'POST' }).catch(() => {})
    setToken(null)
    me.value = null
    await navigateTo('/')
  }

  return { token, me, pendingVerification, setToken, load, login, register, logout }
}
