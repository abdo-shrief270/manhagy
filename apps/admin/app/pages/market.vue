<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-extrabold">
        تقييمات السوق
      </h1>
      <p class="text-sm text-(--ui-text-muted)">
        تقييمات الطلاب على الكورسات. اخفي أي تقييم فيه إساءة أو كلام مش في مكانه، والتقييم المخفي ما بيدخلش في متوسط الكورس.
      </p>
    </div>

    <div class="space-y-4">
      <div class="flex flex-wrap gap-2">
        <USelect v-model="reviewFilter" :items="[{ label: 'الكل', value: 'all' }, { label: 'نجمتين أو أقل', value: 'low' }, { label: 'المخفية', value: 'hidden' }]" class="w-48" />
      </div>
      <p v-if="!reviews.length" class="app-card p-8 text-center text-sm text-(--ui-text-muted)">
        مفيش تقييمات.
      </p>
      <div class="space-y-2">
        <div v-for="r in reviews" :key="r.id" class="app-card space-y-1 p-3 text-sm" :class="r.hidden ? 'opacity-60' : ''">
          <div class="flex flex-wrap items-center gap-2">
            <span class="font-bold">{{ r.first_name }}</span>
            <span class="text-amber-500">{{ '★'.repeat(r.rating) }}<span class="text-(--ui-text-dimmed)">{{ '★'.repeat(5 - r.rating) }}</span></span>
            <span class="text-xs text-(--ui-text-muted)">{{ r.course }} — {{ r.teacher }} · {{ formatDate(r.at) }}</span>
            <UButton class="ms-auto" size="xs" :color="r.hidden ? 'neutral' : 'error'" variant="soft" :label="r.hidden ? 'اظهره' : 'اخفيه'" @click="setHidden(r)" />
          </div>
          <p v-if="r.comment">
            {{ r.comment }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
interface Review { id: string, first_name: string, rating: number, comment: string | null, hidden: boolean, at: string, course: string | null, teacher: string | null }

const api = useAdminApi()
const reviews = ref<Review[]>([])
const reviewFilter = ref<'all' | 'low' | 'hidden'>('all')

async function loadReviews() {
  const query = reviewFilter.value === 'low' ? { max_rating: 2 } : reviewFilter.value === 'hidden' ? { hidden: 1 } : {}
  reviews.value = (await api<{ data: Review[] }>('/market/reviews', { query })).data
}
watch(reviewFilter, loadReviews)
onMounted(loadReviews)

async function setHidden(r: Review) {
  await api(`/market/reviews/${r.id}`, { method: 'PATCH', body: { hidden: !r.hidden } })
  await loadReviews()
}
</script>
