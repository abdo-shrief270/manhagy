<template>
  <div class="space-y-6">
    <div class="space-y-3">
      <h1 class="text-2xl font-extrabold">
        {{ heading }}
      </h1>
      <form class="flex gap-2 sm:hidden" role="search" @submit.prevent="set({ q: q || undefined })">
        <input v-model="q" type="search" class="min-w-0 flex-1 rounded-xl border border-line bg-white px-4 py-2 outline-none focus:border-brand" placeholder="دوّر…" aria-label="بحث">
        <button type="submit" class="btn">
          دوّر
        </button>
      </form>
      <div class="flex flex-wrap gap-2">
        <NuxtLink :to="link({ kind: undefined, node: undefined })" class="chip" :class="!query.kind && !query.node ? 'chip-on' : ''">
          الكل
        </NuxtLink>
        <NuxtLink v-for="(label, key) in KINDS" :key="key" :to="link({ kind: key, node: undefined })" class="chip" :class="query.kind === key ? 'chip-on' : ''">
          {{ label }}
        </NuxtLink>
      </div>
      <div v-if="children?.length" class="flex flex-wrap gap-2">
        <NuxtLink v-for="n in children" :key="n.id" :to="link({ node: n.id })" class="chip">
          {{ n.name }} <span class="num text-muted">{{ formatNumber(n.courses) }}</span>
        </NuxtLink>
      </div>
      <div class="flex flex-wrap items-center gap-2 text-sm">
        <select :value="query.sort ?? 'relevance'" class="rounded-xl border border-line bg-white px-3 py-2" aria-label="الترتيب" @change="set({ sort: ($event.target as HTMLSelectElement).value })">
          <option v-for="(label, key) in SORTS" :key="key" :value="key">
            {{ label }}
          </option>
        </select>
        <select :value="query.price ?? ''" class="rounded-xl border border-line bg-white px-3 py-2" aria-label="السعر" @change="set({ price: ($event.target as HTMLSelectElement).value || undefined })">
          <option value="">
            أي سعر
          </option>
          <option value="free">
            مجاني بس
          </option>
          <option value="paid">
            بفلوس
          </option>
        </select>
        <span v-if="result" class="ms-auto text-muted"><span class="num">{{ formatNumber(result.meta.total) }}</span> كورس</span>
      </div>
    </div>

    <p v-if="result && !result.data.length" class="card p-10 text-center text-muted">
      مفيش كورسات بالبحث ده. جرّب كلمة تانية أو شيل الفلاتر.
    </p>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <CourseTile v-for="c in result?.data ?? []" :key="c.id" :course="c" />
    </div>
    <nav v-if="result && result.meta.last_page > 1" class="flex justify-center gap-2" aria-label="الصفحات">
      <NuxtLink v-if="page > 1" :to="link({ page: String(page - 1) })" class="btn-ghost">
        السابق
      </NuxtLink>
      <span class="num self-center text-muted">{{ page }} / {{ result.meta.last_page }}</span>
      <NuxtLink v-if="page < result.meta.last_page" :to="link({ page: String(page + 1) })" class="btn-ghost">
        التالي
      </NuxtLink>
    </nav>
  </div>
</template>

<script setup lang="ts">
import type { CourseCard, Node } from '~/types'

const SORTS = { relevance: 'الأنسب', rating: 'الأعلى تقييماً', students: 'الأكتر طلاب', new: 'الأحدث', price_asc: 'الأرخص', price_desc: 'الأغلى' }
const KEYS = ['q', 'kind', 'node', 'price', 'sort', 'page'] as const

const route = useRoute()
const query = computed(() => Object.fromEntries(KEYS.map(k => [k, typeof route.query[k] === 'string' && route.query[k] ? String(route.query[k]) : undefined]).filter(([, v]) => v)) as Partial<Record<typeof KEYS[number], string>>)
const page = computed(() => Number(query.value.page ?? 1))
const q = ref(query.value.q ?? '')
watch(() => query.value.q, v => (q.value = v ?? ''))

const { data: result } = await useAsyncData('search', () => marketFetch<{ data: CourseCard[], meta: { total: number, last_page: number } }>('/courses', query.value), { watch: [query] })
// Under a node: its children; with only a kind: that kind's stages / universities / fields.
const ROOTS: Record<string, string> = { school: 'stage', university: 'university', skills: 'field' }
const { data: children } = await useAsyncData('search-nodes', () => {
  const { node, kind } = query.value
  if (!node && !kind) return Promise.resolve([] as Node[])
  return marketFetch<{ data: Node[] }>('/nodes', node ? { parent: node } : {})
    .then(r => r.data.filter(n => n.courses > 0 && (node || n.kind === ROOTS[kind ?? ''])))
}, { watch: [() => query.value.node, () => query.value.kind] })

const heading = computed(() => query.value.q ? `«${query.value.q}»` : query.value.kind ? `كورسات ${KINDS[query.value.kind as keyof typeof KINDS] ?? ''}` : 'كل الكورسات')

function link(change: Record<string, string | undefined>) {
  const next = { ...query.value, page: undefined, ...change }
  return { path: '/search', query: Object.fromEntries(Object.entries(next).filter(([, v]) => v)) }
}

function set(change: Record<string, string | undefined>) {
  navigateTo(link(change))
}

useSeoMeta({ title: () => heading.value, description: 'كورسات أونلاين من مدرسين ومدربين في مصر، بالتقييمات وعدد الطلاب.' })
</script>
