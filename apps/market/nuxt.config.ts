import tailwindcss from '@tailwindcss/vite'

// «سوق منهجي»: the courses every teacher and trainer chose to show, in one place to search, compare
// and read reviews. Server-rendered for search engines and link previews, cached a short while.
// A course opens on its teacher's own platform ({slug}.manhagy.online) with ?via=market.
export default defineNuxtConfig({
  compatibilityDate: '2026-09-01',
  devtools: { enabled: false },
  css: ['~/assets/css/main.css'],
  vite: { plugins: [tailwindcss()] },

  app: {
    head: {
      htmlAttrs: { lang: 'ar', dir: 'rtl' },
      titleTemplate: '%s · سوق منهجي',
      meta: [
        { name: 'viewport', content: 'width=device-width, initial-scale=1' },
        { name: 'format-detection', content: 'telephone=no' },
        { name: 'theme-color', content: '#FAFAFF' },
        { property: 'og:locale', content: 'ar_EG' },
        { property: 'og:site_name', content: 'سوق منهجي' },
      ],
      link: [
        { rel: 'icon', href: '/favicon.svg', type: 'image/svg+xml' },
        { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
        { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' },
        { rel: 'stylesheet', href: 'https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap' },
      ],
    },
  },

  runtimeConfig: {
    // Where this server reaches the API (NUXT_API_INTERNAL; the api container in production).
    apiInternal: 'http://localhost:8000/api/v1',
    public: {
      // This site's own address, for canonical links and the sitemap (NUXT_PUBLIC_MARKET_URL).
      marketUrl: 'https://courses.manhagy.online',
      // The teachers' landing page: «انت مدرس؟ افتح منصتك».
      siteUrl: 'https://manhagy.online',
    },
  },

  routeRules: {
    // Pages re-render at most every 60 s, the same for everyone.
    '/**': { cache: { swr: true, maxAge: 60 } },
    '/api/**': { cache: false },
    '/verify/**': { cache: { swr: true, maxAge: 300 } },
    '/sitemap.xml': { cache: false },
    '/robots.txt': { cache: false },
  },

  nitro: {
    compressPublicAssets: true,
  },

  typescript: { strict: true },
})
