<template>
  <div class="app-shell min-h-dvh flex bg-(--ui-bg-muted)">
    <aside class="app-sidebar sticky top-0 hidden h-dvh w-64 shrink-0 border-e border-(--ui-border) bg-(--ui-bg) px-3 py-4 lg:block">
      <AppSidebar />
    </aside>

    <USlideover v-model:open="menuOpen" side="right" title="القائمة" :ui="{ content: 'max-w-72', body: 'p-3 sm:p-3' }">
      <template #content>
        <div class="app-safe-y h-full bg-(--ui-bg) px-3 py-4">
          <AppSidebar @navigate="menuOpen = false" />
        </div>
      </template>
    </USlideover>

    <div class="flex min-w-0 flex-1 flex-col">
      <header class="app-titlebar sticky top-0 z-20 flex items-center gap-2 border-b border-(--ui-border) bg-(--ui-bg)/90 px-3 backdrop-blur sm:gap-3 lg:px-8">
        <UButton class="lg:hidden" color="neutral" variant="ghost" icon="i-lucide-menu" square aria-label="القائمة" @click="menuOpen = true" />

        <button
          type="button"
          class="flex h-10 min-w-0 flex-1 items-center gap-2 rounded-(--ui-radius) border border-(--ui-border) bg-(--ui-bg-muted) px-3 text-start text-sm text-(--ui-text-muted) transition hover:border-(--ui-border-accented) sm:max-w-md"
          aria-label="بحث سريع"
          @click="searchOpen = true"
        >
          <UIcon name="i-lucide-search" class="size-4 shrink-0" />
          <span class="flex-1 truncate">دوّر على طالب أو صفحة…</span>
          <span class="hidden items-center gap-0.5 sm:flex" dir="ltr">
            <UKbd value="meta" /><UKbd value="K" />
          </span>
        </button>

        <div class="ms-auto flex items-center gap-1 sm:gap-2">
          <UTooltip v-if="siteUrl" text="منصة طلابك">
            <UButton color="neutral" variant="outline" icon="i-lucide-globe" :to="siteUrl" target="_blank" aria-label="منصة طلابك">
              <span class="hidden sm:inline num" dir="ltr">{{ siteHost }}</span>
            </UButton>
          </UTooltip>

          <NotificationBell />

          <UDropdownMenu :items="colorModeItems" :content="{ align: 'end' }">
            <UButton color="neutral" variant="ghost" :icon="colorModeIcon" square aria-label="شكل الألوان" />
          </UDropdownMenu>

          <UDropdownMenu :items="userItems" :content="{ align: 'end' }" :ui="{ content: 'w-60' }">
            <button type="button" class="flex items-center gap-2 rounded-(--ui-radius) p-1 transition hover:bg-(--ui-bg-elevated)" aria-label="حسابك">
              <UAvatar :alt="store.session?.user.name" size="md" />
              <span class="hidden text-start leading-tight md:block">
                <span class="block max-w-32 truncate text-sm font-bold">{{ store.session?.user.name }}</span>
                <span class="block max-w-32 truncate text-xs text-(--ui-text-muted)">{{ roleLabel }}</span>
              </span>
              <UIcon name="i-lucide-chevron-down" class="hidden size-4 text-(--ui-text-muted) md:block" />
            </button>
          </UDropdownMenu>
        </div>
      </header>

      <main class="app-main flex-1 p-4 lg:p-8">
        <BillingSubscriptionBanner />
        <slot />
      </main>
    </div>

    <GlobalSearch v-model:open="searchOpen" />
    <FeedbackModal />
    <InstallAppModal />
    <SecurityLockScreen />
  </div>
</template>

<script setup lang="ts">
import type { DropdownMenuItem } from '@nuxt/ui'

const store = useSessionStore()
const colorMode = useColorMode()

const menuOpen = ref(false)
const searchOpen = ref(false)
const feedback = useFeedback()
const installApp = useInstallApp()

defineShortcuts({
  meta_k: () => {
    searchOpen.value = !searchOpen.value
  },
  '/': () => {
    searchOpen.value = true
  },
})

const roleLabel = computed(() => (store.isOwner ? 'صاحب الحساب' : store.session?.user.role?.name ?? 'مساعد'))

// The students' platform ({slug}.manhagy.online), from GET /site.
const { data: site } = useAsyncData('my-site', () => store.can('site.manage') ? useApi()<{ data: { url: string | null } }>('/site').then(r => r.data) : Promise.resolve(null))
const siteUrl = computed(() => site.value?.url ?? null)
const siteHost = computed(() => siteUrl.value?.replace(/^https?:\/\//, '') ?? '')

const colorModes = [
  { value: 'light', label: 'فاتح', icon: 'i-lucide-sun' },
  { value: 'dark', label: 'غامق', icon: 'i-lucide-moon' },
  { value: 'system', label: 'زي الجهاز', icon: 'i-lucide-monitor' },
] as const

const colorModeIcon = computed(() => colorModes.find(m => m.value === colorMode.preference)?.icon ?? 'i-lucide-sun')
const colorModeItems = computed<DropdownMenuItem[][]>(() => [
  [{ label: 'شكل الألوان', type: 'label' }],
  colorModes.map(mode => ({
    label: mode.label,
    icon: mode.icon,
    type: 'checkbox' as const,
    checked: colorMode.preference === mode.value,
    onSelect: (e: Event) => {
      e.preventDefault()
      colorMode.preference = mode.value
    },
  })),
])

const userItems = computed<DropdownMenuItem[][]>(() => [
  [{
    label: store.session?.user.name ?? '',
    description: [roleLabel.value, store.currentBranch?.name].filter(Boolean).join(' · '),
    avatar: { alt: store.session?.user.name },
    type: 'label',
  }],
  [
    ...(store.can('site.manage') ? [{ label: 'منصتك', icon: 'i-lucide-globe', to: '/settings/site' }] : []),
    ...(store.can('users.manage') ? [{ label: 'المساعدين', icon: 'i-lucide-users-round', to: '/settings/users' }] : []),
    ...(store.isOwner ? [{ label: 'المميزات', icon: 'i-lucide-toggle-right', to: '/settings/features' }, { label: 'الاشتراك والفواتير', icon: 'i-lucide-credit-card', to: '/settings/billing' }] : []),
    { label: 'الإشعارات', icon: 'i-lucide-bell-ring', to: '/settings/notifications' },
    { label: 'الأمان وتسجيل الدخول', icon: 'i-lucide-lock-keyhole', to: '/settings/security' },
    { label: 'بحث سريع', icon: 'i-lucide-search', kbds: ['meta', 'K'], onSelect: () => { searchOpen.value = true } },
  ],
  ...(installApp.installable.value ? [[{ label: 'نزّل التطبيق', description: 'افتح منهجي من أيقونة زي أي برنامج', icon: 'i-lucide-download', onSelect: () => installApp.install() }]] : []),
  [{ label: 'ابعت ملاحظة', description: 'مشكلة، اقتراح، أو سؤال لفريق منهجي', icon: 'i-lucide-message-square-heart', onSelect: () => feedback.show() }],
  [{ label: 'تسجيل الخروج', icon: 'i-lucide-log-out', color: 'error' as const, onSelect: () => store.logout() }],
])

</script>
