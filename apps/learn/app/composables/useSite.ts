import type { SitePublic } from '~/utils/types'

/**
 * Which teacher's platform this is: the host's first label on {slug}.<sitesHost>, else
 * ?site= / NUXT_PUBLIC_DEV_SITE (development), remembered on this device.
 */
export function useSlug(): string {
  const config = useRuntimeConfig().public
  const host = window.location.hostname
  const suffix = `.${String(config.sitesHost)}`
  const sub = host.endsWith(suffix) ? host.slice(0, -suffix.length) : ''
  if (sub && !sub.includes('.')) {
    return sub.toLowerCase()
  }
  const fromQuery = new URLSearchParams(window.location.search).get('site')
  if (fromQuery) {
    localStorage.setItem('manhagy:dev-site', fromQuery)
    return fromQuery.toLowerCase()
  }
  return (localStorage.getItem('manhagy:dev-site') || String(config.devSite) || '').toLowerCase()
}

const COLORS = ['indigo', 'rose', 'teal', 'amber', 'sky', 'violet', 'emerald', 'slate']

/** The platform's public face, loaded once; its colour becomes the app's primary. */
export function useSite() {
  const slug = useState('site-slug', () => useSlug())
  const site = useState<SitePublic | null>('site', () => null)
  const missing = useState('site-missing', () => false)
  const appConfig = useAppConfig()

  async function load() {
    if (site.value || missing.value) return site.value
    if (!slug.value) {
      missing.value = true
      return null
    }
    try {
      site.value = (await useLearnApi()<{ data: SitePublic }>(`/public/sites/${slug.value}`)).data
      if (COLORS.includes(site.value.color)) {
        appConfig.ui.colors.primary = site.value.color === 'slate' ? 'neutral' : site.value.color
      }
      useHead({ title: site.value.title })
    }
    catch {
      missing.value = true
    }
    return site.value
  }

  return { slug, site, missing, load }
}
