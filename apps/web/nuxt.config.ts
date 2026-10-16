// https://nuxt.com/docs/api/configuration/nuxt-config
import { createHash } from 'node:crypto'
import { readdir, readFile, writeFile } from 'node:fs/promises'
import { join, relative, sep } from 'node:path'

async function filesUnder(dir: string): Promise<string[]> {
  const entries = await readdir(dir, { withFileTypes: true }).catch(() => [])
  const nested = await Promise.all(entries.map(e => (e.isDirectory() ? filesUnder(join(dir, e.name)) : [join(dir, e.name)])))
  return nested.flat()
}

export default defineNuxtConfig({
  compatibilityDate: '2026-09-01',
  devtools: { enabled: true },
  modules: ['@nuxt/ui', '@pinia/nuxt'],
  css: ['~/assets/css/main.css'],

  // The dashboard and the POS are a client-rendered app (the POS must work offline later).
  ssr: false,

  app: {
    head: {
      htmlAttrs: { lang: 'ar', dir: 'rtl' },
      title: 'منهجي',
      // viewport-fit=cover: the installed app draws under the notch / home bar (safe-area insets in main.css).
      viewport: 'width=device-width, initial-scale=1, viewport-fit=cover',
      // Installable app (public/site.webmanifest). The theme-color metas (light / dark, or the theme picked
      // in the app) come from plugins/pwa.client.ts. Bump ?v= when the icons change.
      meta: [
        { name: 'application-name', content: 'منهجي' },
        { name: 'mobile-web-app-capable', content: 'yes' },
        { name: 'apple-mobile-web-app-capable', content: 'yes' },
        { name: 'apple-mobile-web-app-title', content: 'منهجي' },
        { name: 'apple-mobile-web-app-status-bar-style', content: 'default' },
        { name: 'format-detection', content: 'telephone=no' },
      ],
      script: [
        // Chrome may offer the install prompt before the app's code runs: keep it for useInstallApp().
        { innerHTML: 'addEventListener("beforeinstallprompt",function(e){e.preventDefault();window.__installPrompt=e})', tagPosition: 'head' },
      ],
      link: [
        { rel: 'manifest', href: '/site.webmanifest' },
        { rel: 'icon', href: '/favicon.ico?v=2', sizes: '48x48' },
        { rel: 'icon', href: '/favicon.svg?v=2', type: 'image/svg+xml' },
        { rel: 'apple-touch-icon', href: '/apple-touch-icon.png?v=2' },
        { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
        { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' },
        { rel: 'stylesheet', href: 'https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap' },
      ],
    },
  },

  // Cairo is loaded from <head>; don't resolve fonts at build time.
  ui: { fonts: false },

  // Light unless the user picks dark (or "follow the device") from the top bar.
  colorMode: { preference: 'light', fallback: 'light', storageKey: 'manhagy-color-mode' },

  // Menu icons come from the API (module manifests), so the bundler can't see them.
  // Keep this list in sync with the icons used in apps/api/app/Modules/*/module.php.
  icon: {
    clientBundle: {
      scan: true,
      // Every icon named in the API's manifests / notifications and in .ts / script code (the scan misses those).
      // Regenerate: grep -rhoE "i-lucide-[a-z0-9-]+" ../api/app/Modules app | sort -u (from apps/web)
      icons: ['lucide:activity', 'lucide:align-right', 'lucide:archive', 'lucide:arrow-down', 'lucide:arrow-right', 'lucide:arrow-up', 'lucide:award', 'lucide:badge-check', 'lucide:badge-percent', 'lucide:ban', 'lucide:banknote', 'lucide:bell', 'lucide:bell-off', 'lucide:bell-ring', 'lucide:blocks', 'lucide:book-open', 'lucide:book-open-text', 'lucide:book-plus', 'lucide:box', 'lucide:bug', 'lucide:calendar', 'lucide:calendar-clock', 'lucide:camera', 'lucide:chart-column', 'lucide:chart-line', 'lucide:check', 'lucide:check-check', 'lucide:chevron-down', 'lucide:chevron-left', 'lucide:chevron-right', 'lucide:chevrons-up-down', 'lucide:circle', 'lucide:circle-check', 'lucide:circle-dashed', 'lucide:circle-help', 'lucide:circle-x', 'lucide:clapperboard', 'lucide:clock', 'lucide:cloud', 'lucide:cloud-upload', 'lucide:copy', 'lucide:credit-card', 'lucide:crown', 'lucide:database', 'lucide:download', 'lucide:ellipsis-vertical', 'lucide:external-link', 'lucide:eye', 'lucide:eye-off', 'lucide:file-spreadsheet', 'lucide:file-text', 'lucide:fingerprint', 'lucide:folder', 'lucide:folder-plus', 'lucide:gift', 'lucide:globe', 'lucide:graduation-cap', 'lucide:hand-coins', 'lucide:handshake', 'lucide:heart-handshake', 'lucide:history', 'lucide:hourglass', 'lucide:image-plus', 'lucide:image-up', 'lucide:info', 'lucide:key-round', 'lucide:layout-dashboard', 'lucide:lightbulb', 'lucide:link', 'lucide:list', 'lucide:list-checks', 'lucide:loader', 'lucide:lock', 'lucide:lock-keyhole', 'lucide:log-out', 'lucide:map-pin-off', 'lucide:menu', 'lucide:message-circle', 'lucide:message-circle-question', 'lucide:message-square-heart', 'lucide:monitor', 'lucide:monitor-down', 'lucide:monitor-smartphone', 'lucide:moon', 'lucide:pen-line', 'lucide:pencil', 'lucide:phone', 'lucide:play', 'lucide:plus', 'lucide:presentation', 'lucide:printer', 'lucide:refresh-cw', 'lucide:repeat', 'lucide:rotate-ccw', 'lucide:scale', 'lucide:scan-line', 'lucide:scan-qr-code', 'lucide:school', 'lucide:search', 'lucide:search-check', 'lucide:send', 'lucide:settings', 'lucide:share', 'lucide:shield', 'lucide:shield-alert', 'lucide:shield-check', 'lucide:sliders-horizontal', 'lucide:smartphone', 'lucide:sparkles', 'lucide:square-plus', 'lucide:star', 'lucide:store', 'lucide:sun', 'lucide:tablet', 'lucide:target', 'lucide:text-cursor-input', 'lucide:ticket', 'lucide:ticket-plus', 'lucide:timer', 'lucide:toggle-right', 'lucide:trash-2', 'lucide:triangle-alert', 'lucide:trophy', 'lucide:undo-2', 'lucide:upload', 'lucide:user', 'lucide:user-check', 'lucide:user-minus', 'lucide:user-plus', 'lucide:user-x', 'lucide:users', 'lucide:users-round', 'lucide:wallet', 'lucide:x'],
    },
  },

  runtimeConfig: {
    public: {
      apiBase: 'http://localhost:8000/api/v1', // NUXT_PUBLIC_API_BASE
      appVersion: '', // NUXT_PUBLIC_APP_VERSION (e.g. the git sha), sent with feedback and error reports
      // The website (links from the sign-up / sign-in pages) and its measurement, used on those two pages
      // only (utils/marketingTags.ts): NUXT_PUBLIC_SITE_URL, NUXT_PUBLIC_GA_ID, NUXT_PUBLIC_META_PIXEL_ID.
      siteUrl: 'https://manhagy.online',
      gaId: '',
      metaPixelId: '',
    },
  },

  typescript: { strict: true },

  hooks: {
    // The service worker (public/sw.js) gets this build's version and its /_nuxt files to precache,
    // so the POS opens offline after one visit and each deploy replaces the old cache.
    async 'nitro:build:public-assets'(nitro) {
      const publicDir = nitro.options.output.publicDir
      const swPath = join(publicDir, 'sw.js')
      const sw = await readFile(swPath, 'utf8').catch(() => null)
      if (sw === null) {
        return
      }
      const assets = (await filesUnder(join(publicDir, '_nuxt')))
        .map(file => '/' + relative(publicDir, file).split(sep).join('/'))
        .filter(url => !url.startsWith('/_nuxt/builds/') && !url.endsWith('.map'))
        .sort()
      const version = createHash('sha256').update(assets.join('\n')).digest('hex').slice(0, 12)
      await writeFile(swPath, sw
        .replace(/^const VERSION = .*$/m, `const VERSION = '${version}'`)
        .replace(/^const PRECACHE = .*$/m, `const PRECACHE = ${JSON.stringify(assets)}`))
    },
  },
})
