<template>
  <div class="max-w-4xl space-y-6">
    <USkeleton v-if="!d" class="h-64" />
    <template v-else>
      <PageHeader :title="d.name" description="كشف حساب الموزّع: الكروت اللي اتسلّمها واللي اتباع واللي اتحصّل.">
        <UButton color="neutral" variant="outline" icon="i-lucide-arrow-right" label="الموزّعين" to="/codes/distributors" />
        <UButton color="neutral" variant="outline" icon="i-lucide-pencil" label="تعديل" @click="formOpen = true" />
        <UButton icon="i-lucide-hand-coins" label="تحصيل" @click="payOpen = true" />
        <UButton v-if="d.phone" color="success" variant="soft" icon="i-lucide-message-circle" label="واتساب" :to="whatsappLink(d.phone, reminder)" target="_blank" />
      </PageHeader>

      <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <StatTile label="اتسلّم" :value="String(d.stats.given)" :hint="`${d.stats.unused} لسه معاه`" icon="i-lucide-ticket" />
        <StatTile label="اتباع" :value="String(d.stats.used)" :hint="formatMoney(d.stats.sold)" icon="i-lucide-circle-check" />
        <StatTile label="اتحصّل" :value="formatMoney(d.stats.collected)" icon="i-lucide-hand-coins" />
        <StatTile label="عليه" :value="formatMoney(d.stats.due)" icon="i-lucide-scale" />
      </div>

      <div class="grid gap-4 lg:grid-cols-2">
        <UCard :ui="{ body: 'p-0 sm:p-0' }">
          <template #header>
            <h2 class="font-bold">
              الدفعات
            </h2>
          </template>
          <p v-if="!d.batches.length" class="p-6 text-center text-sm text-(--ui-text-muted)">
            مفيش دفعات.
          </p>
          <NuxtLink v-for="b in d.batches" :key="b.id" :to="`/codes/${b.id}`" class="flex items-center justify-between border-b border-(--ui-border) px-4 py-2.5 text-sm last:border-0 hover:bg-(--ui-bg-elevated)/50">
            <span class="num font-bold">{{ b.number }}</span>
            <span class="text-(--ui-text-muted)"><span class="num">{{ b.stats.used }}</span> / <span class="num">{{ b.count }}</span> · <span class="num">{{ formatMoney(b.unit_price) }}</span></span>
          </NuxtLink>
        </UCard>
        <UCard :ui="{ body: 'p-0 sm:p-0' }">
          <template #header>
            <h2 class="font-bold">
              التحصيل
            </h2>
          </template>
          <p v-if="!d.payments.length" class="p-6 text-center text-sm text-(--ui-text-muted)">
            لسه ما اتحصّلش حاجة.
          </p>
          <div v-for="p in d.payments" :key="p.id" class="flex items-center gap-3 border-b border-(--ui-border) px-4 py-2.5 text-sm last:border-0">
            <span class="num flex-1 font-bold" :class="p.amount < 0 ? 'text-(--ui-error)' : ''">{{ formatMoney(p.amount) }}</span>
            <span class="text-(--ui-text-muted)">{{ p.note }}</span>
            <span class="num text-xs text-(--ui-text-muted)">{{ formatDate(p.created_at) }}</span>
            <UButton v-if="p.amount > 0 && !reversed.has(p.id)" size="xs" color="neutral" variant="ghost" label="غلط؟" @click="reverse(p.id)" />
          </div>
        </UCard>
      </div>
    </template>

    <UModal v-model:open="payOpen" title="تحصيل من الموزّع">
      <template #body>
        <form class="space-y-4" @submit.prevent="pay">
          <UFormField label="المبلغ (ج)" :description="d ? `عليه ${formatMoney(d.stats.due)}` : undefined">
            <UInputNumber v-model="payment.pounds" :min="1" class="w-full" />
          </UFormField>
          <UFormField label="ملاحظة" hint="اختياري">
            <UInput v-model="payment.note" class="w-full" placeholder="كاش / تحويل" />
          </UFormField>
          <div class="flex justify-end gap-2">
            <UButton color="neutral" variant="ghost" label="إلغاء" @click="payOpen = false" />
            <UButton type="submit" :disabled="!payment.pounds" label="سجّل" />
          </div>
        </form>
      </template>
    </UModal>
    <DistributorForm v-if="d" v-model:open="formOpen" :distributor="d" @saved="() => refresh()" />
  </div>
</template>

<script setup lang="ts">
import type { CodeBatch, Distributor, DistributorStats } from '~/types/manhagy'

definePageMeta({ permission: 'codes.manage' })

type Statement = Distributor & { stats: DistributorStats, batches: (Pick<CodeBatch, 'id' | 'number' | 'kind' | 'count' | 'unit_price' | 'status' | 'created_at' | 'stats'>)[], payments: { id: string, amount: number, reverses_id: string | null, note: string | null, by_name: string | null, created_at: string }[] }

const api = useApi()
const toast = useToast()
const store = useSessionStore()
const id = String(useRoute().params.id)
const { data: d, refresh } = await useAsyncData(`distributor-${id}`, async () => (await api<{ data: Statement }>(`/codes/distributors/${id}`)).data)
const reversed = computed(() => new Set((d.value?.payments ?? []).map(p => p.reverses_id).filter(Boolean)))
const reminder = computed(() => `أهلاً يا ${d.value?.name}، ده حساب كروت ${store.session?.tenant.name}: اتباع ${d.value?.stats.used} كارت، والمطلوب ${formatMoney(d.value?.stats.due ?? 0)}.`)

const formOpen = ref(false)
const payOpen = ref(false)
const payment = reactive({ pounds: 0, note: '' })
async function pay() {
  try {
    await api(`/codes/distributors/${id}/payments`, { method: 'POST', body: { amount: Math.round(payment.pounds * 100), note: payment.note || null } })
    payOpen.value = false
    Object.assign(payment, { pounds: 0, note: '' })
    await refresh()
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
}
async function reverse(paymentId: string) {
  const note = prompt('ليه بتلغي التحصيل ده؟')
  if (!note?.trim()) return
  try {
    await api(`/codes/distributors/${id}/payments/${paymentId}/reverse`, { method: 'POST', body: { note } })
    await refresh()
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
}
</script>
