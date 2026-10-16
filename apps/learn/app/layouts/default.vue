<template>
  <div class="min-h-dvh bg-(--ui-bg-muted)">
    <header class="sticky top-0 z-20 border-b border-(--ui-border) bg-(--ui-bg)/90 backdrop-blur">
      <div class="mx-auto flex h-16 max-w-5xl items-center gap-3 px-4">
        <NuxtLink to="/" class="flex min-w-0 items-center gap-2">
          <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-primary text-lg font-extrabold text-white">{{ initial }}</span>
          <span class="min-w-0">
            <span class="block truncate font-extrabold leading-tight">{{ site?.title }}</span>
            <span v-if="site?.tagline" class="block truncate text-xs text-(--ui-text-muted)">{{ site.tagline }}</span>
          </span>
        </NuxtLink>
        <div class="ms-auto flex items-center gap-1">
          <template v-if="learner.me.value">
            <UButton to="/redeem" color="neutral" variant="ghost" icon="i-lucide-ticket" class="hidden sm:inline-flex" label="عندي كود" />
            <UButton to="/wallet" color="neutral" variant="ghost" icon="i-lucide-wallet" class="hidden sm:inline-flex" label="المحفظة" />
            <UDropdownMenu :items="menu" :content="{ align: 'end' }">
              <UButton color="neutral" variant="ghost" class="gap-2">
                <UAvatar :alt="learner.me.value.name" size="sm" />
                <span class="hidden max-w-28 truncate sm:inline">{{ learner.me.value.name }}</span>
              </UButton>
            </UDropdownMenu>
          </template>
          <template v-else>
            <UButton to="/login" color="neutral" variant="ghost" label="دخول" />
            <UButton v-if="site?.registration_open" to="/register" label="سجّل" />
          </template>
        </div>
      </div>
    </header>

    <div v-if="learner.me.value && !learner.me.value.phone_verified && route.path !== '/verify'" class="border-b border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950">
      <div class="mx-auto flex max-w-5xl flex-wrap items-center gap-2 px-4 py-2 text-sm">
        <UIcon name="i-lucide-shield-alert" class="size-4 text-amber-600" />
        أكّد رقمك برسالة واتساب عشان تقدر تتفرّج وتستخدم الأكواد.
        <UButton to="/verify" size="xs" color="warning" class="ms-auto" label="أكّد دلوقتي" />
      </div>
    </div>

    <main class="mx-auto max-w-5xl px-4 py-6 pb-24">
      <slot />
    </main>

    <!-- Phones: the main places at the thumb. -->
    <nav v-if="learner.me.value" class="fixed inset-x-0 bottom-0 z-20 grid grid-cols-4 border-t border-(--ui-border) bg-(--ui-bg) pb-[env(safe-area-inset-bottom)] sm:hidden">
      <NuxtLink v-for="t in tabs" :key="t.to" :to="t.to" class="flex flex-col items-center gap-0.5 py-2 text-xs" :class="route.path === t.to ? 'text-primary font-bold' : 'text-(--ui-text-muted)'">
        <UIcon :name="t.icon" class="size-5" />
        {{ t.label }}
      </NuxtLink>
    </nav>
  </div>
</template>

<script setup lang="ts">
import type { DropdownMenuItem } from '@nuxt/ui'

const { site } = useSite()
const learner = useLearner()
const route = useRoute()
const initial = computed(() => (site.value?.title ?? 'م').replace(/^(أ\.|م\.|د\.)\s*/, '').charAt(0))

const tabs = [
  { to: '/', label: 'كورساتي', icon: 'i-lucide-book-open-text' },
  { to: '/redeem', label: 'عندي كود', icon: 'i-lucide-ticket' },
  { to: '/wallet', label: 'المحفظة', icon: 'i-lucide-wallet' },
  { to: '/me', label: 'حسابي', icon: 'i-lucide-user' },
]

const menu = computed<DropdownMenuItem[][]>(() => [
  [{ label: learner.me.value?.name ?? '', description: localPhone(learner.me.value?.phone), type: 'label' }],
  [
    { label: 'حسابي وأجهزتي', icon: 'i-lucide-user', to: '/me' },
    { label: 'عندي كود', icon: 'i-lucide-ticket', to: '/redeem' },
    { label: 'المحفظة', icon: 'i-lucide-wallet', to: '/wallet' },
    { label: 'كارنيه السنتر', icon: 'i-lucide-qr-code', to: '/card' },
  ],
  [{ label: 'خروج', icon: 'i-lucide-log-out', color: 'error' as const, onSelect: () => learner.logout() }],
])
</script>
