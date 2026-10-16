<template>
  <div class="max-w-5xl space-y-6">
    <PageHeader title="سوق منهجي" description="مكان واحد الطلاب بيدوّروا فيه على كورس من كل المدرسين والمدربين. البيع اللي جاي من السوق عليه عمولة، واللي جاي من لينكك انت ببلاش.">
      <UButton v-if="state?.market_url" color="neutral" variant="outline" icon="i-lucide-external-link" label="افتح السوق" :to="state.market_url" target="_blank" />
    </PageHeader>

    <USkeleton v-if="!state || !form" class="h-96" />
    <template v-else>
      <UAlert v-if="state.blocker === 'unpaid'" color="error" variant="subtle" icon="i-lucide-circle-alert" title="كورساتك اتشالت من السوق مؤقتاً" :description="`فيه عمولة ${formatMoney(state.commission.due)} عدّى عليها ${state.commission.pay_within_days} يوم. ادفعها وكورساتك ترجع على طول.`" />
      <UAlert v-else-if="state.blocker === 'subscription'" color="warning" variant="subtle" icon="i-lucide-credit-card" title="اشتراكك محتاج تجديد" description="السوق بيعرض الحسابات اللي اشتراكها شغال بس." :actions="[{ label: 'جدّد', to: '/settings/billing' }]" />
      <UAlert v-else-if="state.blocker === null" color="success" variant="subtle" icon="i-lucide-badge-check" :title="`${inMarket} كورس ظاهر في السوق دلوقتي`" />

      <div class="grid gap-6 lg:grid-cols-3">
        <form class="space-y-4 lg:col-span-2" @submit.prevent="save">
          <UCard :ui="{ body: 'space-y-4' }">
            <USwitch v-model="form.listed" size="lg" label="اظهر في سوق منهجي" description="كورساتك المنشورة تظهر للطلاب في البحث، بتقييماتها وعدد المشتركين. تقدر تخبّي أي كورس تحت." />
            <UFormField label="جملة تحت اسمك" hint="اختياري" description="بتظهر على كل كورس من كورساتك في السوق.">
              <UInput :model-value="form.headline ?? ''" class="w-full" maxlength="120" placeholder="مدرس فيزياء تالتة ثانوي — 15 سنة خبرة" @update:model-value="v => form!.headline = String(v) || null" />
            </UFormField>
            <UFormField label="نبذة عنك" hint="اختياري" description="في صفحتك في السوق.">
              <UTextarea :model-value="form.about ?? ''" :rows="4" autoresize class="w-full" maxlength="2000" @update:model-value="v => form!.about = String(v) || null" />
            </UFormField>
          </UCard>

          <UCard :ui="{ body: 'p-0 sm:p-0' }">
            <template #header>
              <h2 class="font-bold">
                الكورسات
              </h2>
            </template>
            <p v-if="!state.courses.length" class="p-6 text-center text-sm text-(--ui-text-muted)">
              انشر كورس الأول.
            </p>
            <div class="divide-y divide-(--ui-border)">
              <div v-for="c in state.courses" :key="c.id" class="flex flex-wrap items-center gap-3 px-4 py-3">
                <USwitch :model-value="!form.hidden_courses.includes(c.id)" :aria-label="`اظهر ${c.title}`" @update:model-value="v => toggle(c.id, v)" />
                <div class="min-w-0 flex-1">
                  <p class="truncate font-bold">
                    {{ c.title }}
                  </p>
                  <p class="text-xs text-(--ui-text-muted)">
                    <span class="num">{{ formatMoney(c.price) }}</span>
                    · <span class="num">{{ c.students }}</span> طالب
                    <template v-if="c.rating !== null">
                      · <UIcon name="i-lucide-star" class="size-3 text-amber-500" /> <span class="num">{{ c.rating.toFixed(1) }}</span> (<span class="num">{{ c.reviews }}</span>)
                    </template>
                  </p>
                </div>
                <UBadge v-if="c.in_market" color="success" variant="subtle" label="في السوق" />
                <UButton v-if="c.url" size="xs" color="neutral" variant="ghost" icon="i-lucide-external-link" :to="c.url" target="_blank" aria-label="صفحته في السوق" />
              </div>
            </div>
          </UCard>
          <UButton type="submit" size="lg" :loading="saving" label="حفظ" />
        </form>

        <div class="space-y-4">
          <UCard :ui="{ body: 'space-y-3' }">
            <template #header>
              <h2 class="font-bold">
                العمولة
              </h2>
            </template>
            <p class="text-sm text-(--ui-text-muted)">
              <span class="num font-bold text-(--ui-text)">{{ state.commission.rate_percent }}%</span> من الكورس اللي طالب اشتراه وهو جاي من السوق. اللي بييجي من لينك منصتك أو بكود مالوش عمولة.
            </p>
            <div class="rounded-(--ui-radius) bg-(--ui-bg-elevated) p-3">
              <p class="text-xs text-(--ui-text-muted)">
                عليك دلوقتي
              </p>
              <p class="num text-2xl font-extrabold" :class="state.commission.overdue ? 'text-error' : ''">
                {{ formatMoney(state.commission.due) }}
              </p>
              <p v-if="state.commission.pay_by" class="text-xs text-(--ui-text-muted)">
                ادفع قبل {{ formatDate(state.commission.pay_by) }}
              </p>
            </div>
            <div v-if="state.commission.due && state.commission.instapay" class="space-y-1 text-sm">
              <p class="font-bold">
                حوّل InstaPay على:
              </p>
              <p v-if="state.commission.instapay.address" class="num" dir="ltr">
                {{ state.commission.instapay.address }}
              </p>
              <p v-if="state.commission.instapay.name">
                {{ state.commission.instapay.name }}
              </p>
              <p class="text-xs text-(--ui-text-muted)">
                وابعت رقم التحويل لخدمة العملاء من «ابعت ملاحظة».
              </p>
            </div>
            <p class="text-xs text-(--ui-text-muted)">
              بعت عن طريق السوق: <span class="num">{{ formatMoney(state.commission.sales) }}</span> · دفعت: <span class="num">{{ formatMoney(state.commission.settled) }}</span>
            </p>
          </UCard>
        </div>
      </div>

      <UTabs v-model="tab" :items="[{ label: 'التقييمات', value: 'reviews' }, { label: 'العمولات', value: 'commissions' }]" class="w-full" />
      <div v-if="tab === 'reviews'" class="space-y-2">
        <p v-if="!reviews.length" class="app-card p-6 text-center text-sm text-(--ui-text-muted)">
          لسه مفيش تقييمات. الطلاب المشتركين بيقيّموا من صفحة الكورس في منصتك.
        </p>
        <div v-for="r in reviews" :key="r.id" class="app-card space-y-1 p-3 text-sm" :class="r.hidden ? 'opacity-60' : ''">
          <div class="flex items-center gap-2">
            <span class="font-bold">{{ r.first_name }}</span>
            <span class="text-amber-500" :aria-label="`${r.rating} من 5`">{{ '★'.repeat(r.rating) }}<span class="text-(--ui-text-dimmed)">{{ '★'.repeat(5 - r.rating) }}</span></span>
            <span class="text-xs text-(--ui-text-muted)">{{ courseTitle(r.course_id) }} · {{ timeAgo(r.at) }}</span>
            <UBadge v-if="r.hidden" size="sm" color="neutral" variant="subtle" label="مخفي من الإدارة" />
          </div>
          <p v-if="r.comment">
            {{ r.comment }}
          </p>
        </div>
      </div>
      <div v-else class="app-card overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="text-(--ui-text-muted)">
            <tr class="text-start">
              <th class="p-3 text-start">
                التاريخ
              </th>
              <th class="p-3 text-start">
                الكورس
              </th>
              <th class="p-3 text-start">
                البيع
              </th>
              <th class="p-3 text-start">
                العمولة
              </th>
              <th class="p-3 text-start" />
            </tr>
          </thead>
          <tbody class="divide-y divide-(--ui-border)">
            <tr v-if="!commissions.length">
              <td colspan="5" class="p-6 text-center text-(--ui-text-muted)">
                لسه مفيش بيع من السوق.
              </td>
            </tr>
            <tr v-for="c in commissions" :key="c.id">
              <td class="num p-3">
                {{ formatDate(c.created_at) }}
              </td>
              <td class="p-3">
                {{ c.course_title }}
              </td>
              <td class="num p-3">
                {{ formatMoney(c.amount) }}
              </td>
              <td class="num p-3 font-bold">
                {{ formatMoney(c.commission) }}
              </td>
              <td class="p-3">
                <UBadge :color="c.state === 'due' ? 'warning' : c.state === 'settled' ? 'success' : 'neutral'" variant="subtle" :label="{ due: 'عليك', settled: 'اتدفعت', void: 'اتلغت' }[c.state]" />
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { CourseReview, MarketCommission, MarketState } from '~/types/manhagy'

definePageMeta({ permission: 'marketplace.manage' })

const api = useApi()
const toast = useToast()
const state = ref<MarketState | null>(null)
const form = ref<Pick<MarketState, 'listed' | 'headline' | 'about' | 'hidden_courses'> | null>(null)
const saving = ref(false)
const tab = ref<'reviews' | 'commissions'>('reviews')
const reviews = ref<CourseReview[]>([])
const commissions = ref<MarketCommission[]>([])

const inMarket = computed(() => state.value?.courses.filter(c => c.in_market).length ?? 0)

function fill(data: MarketState) {
  state.value = data
  form.value = { listed: data.listed, headline: data.headline, about: data.about, hidden_courses: [...data.hidden_courses] }
}

function toggle(id: string, shown: boolean) {
  if (!form.value) return
  form.value.hidden_courses = shown ? form.value.hidden_courses.filter(c => c !== id) : [...form.value.hidden_courses, id]
}

function courseTitle(id: string) {
  return state.value?.courses.find(c => c.id === id)?.title ?? ''
}

onMounted(async () => {
  fill((await api<{ data: MarketState }>('/marketplace')).data)
  reviews.value = (await api<{ data: CourseReview[] }>('/marketplace/reviews')).data
  commissions.value = (await api<{ data: MarketCommission[] }>('/marketplace/commissions')).data
})

async function save() {
  if (!form.value) return
  saving.value = true
  try {
    fill((await api<{ data: MarketState }>('/marketplace', { method: 'PUT', body: form.value })).data)
    toast.add({ color: 'success', title: form.value.listed ? 'اتحفظ — كورساتك في السوق' : 'اتحفظ' })
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    saving.value = false
  }
}
</script>
