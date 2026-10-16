<template>
  <header class="sticky top-0 z-20 border-b border-line bg-paper/90 backdrop-blur">
    <div class="mx-auto flex max-w-6xl items-center gap-3 px-4 py-3">
      <NuxtLink to="/" class="flex items-center gap-2 text-ink hover:text-ink">
        <img src="/favicon.svg" alt="" class="size-8">
        <span class="text-lg font-extrabold">سوق <span class="text-brand">منهجي</span></span>
      </NuxtLink>
      <form class="ms-auto hidden max-w-md flex-1 sm:block" role="search" @submit.prevent="go">
        <input v-model="q" type="search" class="w-full rounded-xl border border-line bg-white px-4 py-2 outline-none focus:border-brand" placeholder="دوّر على كورس، مادة، أو مدرس…" aria-label="بحث">
      </form>
      <NuxtLink to="/search" class="btn-ghost ms-auto sm:hidden" aria-label="بحث">
        بحث
      </NuxtLink>
      <NuxtLink to="/verify" class="hidden text-sm font-bold text-muted hover:text-brand md:block">
        اتأكد من شهادة
      </NuxtLink>
    </div>
  </header>
</template>

<script setup lang="ts">
const route = useRoute()
const q = ref(typeof route.query.q === 'string' ? route.query.q : '')
watch(() => route.query.q, v => (q.value = typeof v === 'string' ? v : ''))

function go() {
  navigateTo({ path: '/search', query: q.value.trim() ? { q: q.value.trim() } : {} })
}
</script>
