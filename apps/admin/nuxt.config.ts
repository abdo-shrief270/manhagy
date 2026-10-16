// The platform admin panel: its own app, built and served on its own domain (ADMIN_DOMAIN),
// so none of it ships to shop users. Talks only to /api/v1/admin/*.
export default defineNuxtConfig({
  compatibilityDate: '2026-09-01',
  devtools: { enabled: false },
  modules: ['@nuxt/ui'],
  css: ['~/assets/css/main.css'],
  ssr: false,

  app: {
    head: {
      htmlAttrs: { lang: 'ar', dir: 'rtl' },
      title: 'منهجي — الإدارة',
      meta: [{ name: 'robots', content: 'noindex, nofollow' }],
      link: [
        { rel: 'icon', href: '/favicon.ico?v=2', sizes: '48x48' },
        { rel: 'icon', href: '/favicon.svg?v=2', type: 'image/svg+xml' },
        { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
        { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' },
        { rel: 'stylesheet', href: 'https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap' },
      ],
    },
  },

  ui: { fonts: false },
  colorMode: { preference: 'light', fallback: 'light', storageKey: 'manhagy-admin-color-mode' },

  icon: {
    clientBundle: {
      scan: true,
      icons: ['lucide:arrow-left', 'lucide:arrow-right', 'lucide:banknote', 'lucide:blocks', 'lucide:bug', 'lucide:check', 'lucide:chevron-left', 'lucide:circle-check', 'lucide:circle-x', 'lucide:clock', 'lucide:copy', 'lucide:crown', 'lucide:eye-off', 'lucide:gift', 'lucide:handshake', 'lucide:history', 'lucide:image', 'lucide:layout-dashboard', 'lucide:log-out', 'lucide:menu', 'lucide:message-circle', 'lucide:message-square-heart', 'lucide:moon', 'lucide:network', 'lucide:pencil', 'lucide:plus', 'lucide:rotate-ccw', 'lucide:search', 'lucide:server', 'lucide:shield', 'lucide:shopping-bag', 'lucide:store', 'lucide:sun', 'lucide:tags', 'lucide:ticket-percent', 'lucide:trash-2', 'lucide:user-plus', 'lucide:x'],
    },
  },

  runtimeConfig: {
    public: {
      apiBase: 'http://localhost:8000/api/v1', // NUXT_PUBLIC_API_BASE
    },
  },

  typescript: { strict: true },
})
