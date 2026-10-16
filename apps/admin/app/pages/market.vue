<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-extrabold">
        سوق منهجي
      </h1>
      <p class="text-sm text-(--ui-text-muted)">
        العمولة على البيع اللي جاي من السوق. الحساب اللي عليه عمولة عدّى عليها مدة السداد بيتشال من السوق لحد ما تسجّل إنه دفع.
      </p>
    </div>

    <div class="flex gap-1 rounded-(--ui-radius) bg-(--ui-bg-elevated) p-1 w-fit">
      <UButton size="sm" :color="tab === 'commissions' ? 'primary' : 'neutral'" :variant="tab === 'commissions' ? 'solid' : 'ghost'" label="العمولات" @click="tab = 'commissions'" />
      <UButton size="sm" :color="tab === 'reviews' ? 'primary' : 'neutral'" :variant="tab === 'reviews' ? 'solid' : 'ghost'" label="التقييمات" @click="tab = 'reviews'" />
    </div>

    <template v-if="tab === 'commissions'">
      <p v-if="!owing.length" class="app-card p-8 text-center text-sm text-(--ui-text-muted)">
        مفيش حد عليه عمولة.
      </p>
      <div class="space-y-2">
        <div v-for="o in owing" :key="o.tenant_id" class="app-card flex flex-wrap items-center gap-3 p-3 text-sm">
          <div class="min-w-0 flex-1">
            <p class="font-bold">
              {{ o.name }} <span v-if="o.code" class="num text-xs text-(--ui-text-muted)">{{ o.code }}</span>
            </p>
            <p class="text-(--ui-text-muted)">
              <span class="num">{{ o.due_count }}</span> عملية · أقدمها {{ formatDate(o.oldest_due_at) }}
            </p>
          </div>
          <UBadge v-if="o.overdue" color="error" variant="subtle" label="متأخر — اتشال من السوق" />
          <span class="num text-lg font-extrabold">{{ formatMoney(o.due) }}</span>
          <UButton size="sm" variant="soft" label="التفاصيل" @click="open(o)" />
        </div>
      </div>
    </template>

    <template v-else>
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
    </template>

    <UModal v-model:open="detailOpen" :title="detail?.name ?? ''" :ui="{ content: 'sm:max-w-2xl' }">
      <template #body>
        <div v-if="detail" class="space-y-4">
          <div class="grid grid-cols-3 gap-2 text-center text-sm">
            <div class="rounded-(--ui-radius) bg-(--ui-bg-elevated) p-3">
              <p class="text-(--ui-text-muted)">
                عليه
              </p>
              <p class="num text-lg font-extrabold">
                {{ formatMoney(detail.summary.due) }}
              </p>
            </div>
            <div class="rounded-(--ui-radius) bg-(--ui-bg-elevated) p-3">
              <p class="text-(--ui-text-muted)">
                دفع
              </p>
              <p class="num text-lg font-extrabold">
                {{ formatMoney(detail.summary.settled) }}
              </p>
            </div>
            <div class="rounded-(--ui-radius) bg-(--ui-bg-elevated) p-3">
              <p class="text-(--ui-text-muted)">
                بيع من السوق
              </p>
              <p class="num text-lg font-extrabold">
                {{ formatMoney(detail.summary.sales) }}
              </p>
            </div>
          </div>
          <form v-if="detail.summary.due" class="flex flex-wrap items-end gap-2" @submit.prevent="settle">
            <UFormField label="رقم التحويل" class="flex-1">
              <UInput v-model="reference" class="w-full" dir="ltr" placeholder="InstaPay reference" />
            </UFormField>
            <UButton type="submit" :disabled="!reference.trim()" :loading="busy" :label="`سجّل إنه دفع ${formatMoney(detail.summary.due)}`" />
          </form>
          <div class="max-h-80 divide-y divide-(--ui-border) overflow-y-auto text-sm">
            <div v-for="c in detail.rows" :key="c.id" class="flex items-center gap-2 py-2">
              <span class="num w-24 text-(--ui-text-muted)">{{ formatDate(c.created_at) }}</span>
              <span class="flex-1 truncate">{{ c.course_title }}</span>
              <span class="num">{{ formatMoney(c.commission) }}</span>
              <UBadge size="sm" variant="subtle" :color="c.state === 'due' ? 'warning' : c.state === 'settled' ? 'success' : 'neutral'" :label="{ due: 'عليه', settled: 'اتدفعت', void: 'اتلغت' }[c.state]" />
              <UButton v-if="c.state === 'due'" size="xs" color="neutral" variant="ghost" label="الغيها" @click="voidOne(c.id)" />
            </div>
          </div>
        </div>
      </template>
    </UModal>
  </div>
</template>

<script setup lang="ts">
interface Owing { tenant_id: string, name: string, code: string | null, due: number, due_count: number, oldest_due_at: string, overdue: boolean }
interface Commission { id: string, course_title: string, commission: number, state: 'due' | 'settled' | 'void', created_at: string }
interface Detail { name: string, summary: { due: number, settled: number, sales: number }, rows: Commission[] }
interface Review { id: string, first_name: string, rating: number, comment: string | null, hidden: boolean, at: string, course: string | null, teacher: string | null }

const api = useAdminApi()
const toast = useToast()
const tab = ref<'commissions' | 'reviews'>('commissions')
const owing = ref<Owing[]>([])
const reviews = ref<Review[]>([])
const reviewFilter = ref<'all' | 'low' | 'hidden'>('all')
const detailOpen = ref(false)
const detail = ref<Detail | null>(null)
const detailTenant = ref('')
const reference = ref('')
const busy = ref(false)

async function loadOwing() {
  owing.value = (await api<{ data: Owing[] }>('/market/commissions')).data
}
async function loadReviews() {
  const query = reviewFilter.value === 'low' ? { max_rating: 2 } : reviewFilter.value === 'hidden' ? { hidden: 1 } : {}
  reviews.value = (await api<{ data: Review[] }>('/market/reviews', { query })).data
}
watch(reviewFilter, loadReviews)
watch(tab, t => t === 'reviews' ? loadReviews() : loadOwing())
onMounted(loadOwing)

async function open(o: Owing) {
  detailTenant.value = o.tenant_id
  reference.value = ''
  detail.value = (await api<{ data: Detail }>(`/market/commissions/${o.tenant_id}`)).data
  detailOpen.value = true
}

async function settle() {
  busy.value = true
  try {
    await api(`/market/commissions/${detailTenant.value}/settle`, { method: 'POST', body: { reference: reference.value.trim() } })
    toast.add({ color: 'success', title: 'اتسجّل الدفع — كورساته رجعت السوق لو كانت اتشالت' })
    detailOpen.value = false
    await loadOwing()
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    busy.value = false
  }
}

async function voidOne(id: string) {
  const reason = prompt('ليه بتلغي العمولة دي؟')
  if (!reason?.trim()) return
  await api(`/market/commissions/${id}/void`, { method: 'POST', body: { reason: reason.trim() } })
  detail.value = (await api<{ data: Detail }>(`/market/commissions/${detailTenant.value}`)).data
  await loadOwing()
}

async function setHidden(r: Review) {
  await api(`/market/reviews/${r.id}`, { method: 'PATCH', body: { hidden: !r.hidden } })
  await loadReviews()
}
</script>
