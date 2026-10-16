// The students' platform: one app for every teacher, the teacher picked by the host ({slug}.manhagy.online).
export default defineNuxtConfig({
  compatibilityDate: '2026-09-01',
  devtools: { enabled: false },
  modules: ['@nuxt/ui'],
  css: ['~/assets/css/main.css'],
  ssr: false,

  app: {
    head: {
      htmlAttrs: { lang: 'ar', dir: 'rtl' },
      title: 'منهجي',
      viewport: 'width=device-width, initial-scale=1, viewport-fit=cover',
      meta: [
        { name: 'robots', content: 'noindex' },
        { name: 'format-detection', content: 'telephone=no' },
      ],
      link: [
        { rel: 'icon', href: '/favicon.svg', type: 'image/svg+xml' },
        { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
        { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' },
        { rel: 'stylesheet', href: 'https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap' },
      ],
    },
  },

  ui: { fonts: false },
  colorMode: { preference: 'light', fallback: 'light', storageKey: 'manhagy-learn-color-mode' },

  icon: {
    clientBundle: {
      scan: true,
      // Icons named in script code (the scan misses those). Regenerate: grep -rhoE "i-lucide-[a-z0-9-]+" app | sort -u
      icons: ['lucide:arrow-right', 'lucide:book-open-text', 'lucide:check', 'lucide:chevron-left', 'lucide:circle-check', 'lucide:circle-dashed', 'lucide:circle-pause', 'lucide:circle-x', 'lucide:clapperboard', 'lucide:external-link', 'lucide:file-text', 'lucide:hourglass', 'lucide:list-checks', 'lucide:loader-circle', 'lucide:lock', 'lucide:log-out', 'lucide:message-circle', 'lucide:party-popper', 'lucide:play', 'lucide:qr-code', 'lucide:send', 'lucide:shield-alert', 'lucide:shopping-cart', 'lucide:smartphone', 'lucide:ticket', 'lucide:user', 'lucide:user-plus', 'lucide:wallet'],
    },
  },

  runtimeConfig: {
    public: {
      apiBase: 'http://localhost:8000/api/v1', // NUXT_PUBLIC_API_BASE
      sitesHost: 'manhagy.online', // NUXT_PUBLIC_SITES_HOST: {slug}.<this>
      devSite: '', // NUXT_PUBLIC_DEV_SITE: the slug to use on localhost
    },
  },

  typescript: { strict: true },
})
