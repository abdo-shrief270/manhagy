<template>
  <div class="space-y-12">
    <section class="space-y-6 rounded-3xl bg-brand-soft px-6 py-10 text-center sm:px-12 sm:py-16">
      <h1 class="text-3xl font-extrabold leading-tight sm:text-5xl">
        كل الكورسات <span class="text-brand">في مكان واحد</span>
      </h1>
      <p class="mx-auto max-w-2xl text-muted sm:text-lg">
        دوّر على مدرس مادتك أو كورس مهارة، قارن الأسعار، واقرا رأي الطلاب اللي اشتركوا قبلك.
      </p>
      <form class="mx-auto flex max-w-xl gap-2" role="search" @submit.prevent="search">
        <input v-model="q" type="search" class="min-w-0 flex-1 rounded-xl border border-line bg-white px-4 py-3 text-lg outline-none focus:border-brand" placeholder="فيزياء تالتة ثانوي، Python، IELTS…" aria-label="بحث">
        <button type="submit" class="btn">
          دوّر
        </button>
      </form>
      <div v-if="home" class="flex flex-wrap justify-center gap-2">
        <NuxtLink v-for="(label, key) in KINDS" v-show="home.kinds[key]" :key="key" :to="{ path: '/search', query: { kind: key } }" class="chip">
          {{ label }} <span class="num text-muted">{{ formatNumber(home.kinds[key] ?? 0) }}</span>
        </NuxtLink>
      </div>
    </section>

    <section v-if="roots?.length" class="space-y-3">
      <h2 class="text-xl font-extrabold">
        اختار مرحلتك أو مجالك
      </h2>
      <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
        <NuxtLink v-for="n in roots" :key="n.id" :to="{ path: '/search', query: { node: n.id } }" class="card flex items-center justify-between gap-2 p-4 font-bold text-ink hover:border-brand hover:text-brand">
          {{ n.name }}
          <span class="num text-sm font-normal text-muted">{{ formatNumber(n.courses) }}</span>
        </NuxtLink>
      </div>
    </section>

    <template v-if="home">
      <section v-for="block in blocks" :key="block.title" class="space-y-3">
        <div class="flex items-end justify-between">
          <h2 class="text-xl font-extrabold">
            {{ block.title }}
          </h2>
          <NuxtLink :to="{ path: '/search', query: { sort: block.sort } }" class="text-sm font-bold">
            الكل
          </NuxtLink>
        </div>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <CourseTile v-for="c in block.courses" :key="c.id" :course="c" />
        </div>
      </section>
      <p v-if="!home.courses" class="card p-10 text-center text-muted">
        السوق لسه بيفتح — أول الكورسات جاية قريب.
      </p>
    </template>

    <section class="card flex flex-col items-center gap-4 p-8 text-center sm:flex-row sm:text-start">
      <div class="flex-1 space-y-1">
        <h2 class="text-xl font-extrabold">
          انت مدرس أو مدرب؟
        </h2>
        <p class="text-muted">
          افتح منصتك باسمك على منهجي، واظهر هنا قدام كل الطلاب. البيع اللي جاي من لينكك انت مالوش عمولة.
        </p>
      </div>
      <a :href="siteUrl" class="btn">ابدأ ببلاش</a>
    </section>
  </div>
</template>

<script setup lang="ts">
import type { Home, Node } from '~/types'

const siteUrl = String(useRuntimeConfig().public.siteUrl)
const q = ref('')
const { data: home } = await useAsyncData('home', () => marketFetch<{ data: Home }>('/home').then(r => r.data))
const { data: roots } = await useAsyncData('roots', () => marketFetch<{ data: Node[] }>('/nodes').then(r => r.data.filter(n => n.courses > 0)))

const blocks = computed(() => home.value
  ? [
      { title: 'الأعلى تقييماً', sort: 'rating', courses: home.value.top_rated },
      { title: 'الأكتر طلاب', sort: 'students', courses: home.value.popular },
      { title: 'وصل جديد', sort: 'new', courses: home.value.newest },
    ].filter(b => b.courses.length)
  : [])

function search() {
  navigateTo({ path: '/search', query: q.value.trim() ? { q: q.value.trim() } : {} })
}

useSeoMeta({
  title: 'كورسات أونلاين لكل المراحل والمهارات',
  description: 'دوّر على مدرس مادتك أو كورس مهارة في مصر: ثانوي، إعدادي، جامعة، برمجة، لغات، تصميم. قارن الأسعار واقرا تقييمات الطلاب.',
})
</script>
