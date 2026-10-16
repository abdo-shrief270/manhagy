<template>
  <div class="max-w-4xl space-y-6">
    <PageHeader title="الدرج" description="الفلوس اللي اتحصّلت على الباب ولسه ما اتسلّمتش. لما تسلّمها، المستلم بيعدّها والفرق بيتسجّل." />

    <div v-if="mine" class="app-card flex items-center justify-between p-5">
      <div>
        <p class="text-sm text-(--ui-text-muted)">
          معاك دلوقتي
        </p>
        <p class="num text-3xl font-extrabold">
          {{ formatMoney(mine.in_hand) }}
        </p>
      </div>
      <span class="text-sm text-(--ui-text-muted)"><span class="num">{{ mine.payments.length }}</span> عملية</span>
    </div>

    <UCard v-if="store.can('center.cash') && all" :ui="{ body: 'p-0 sm:p-0' }">
      <template #header>
        <h2 class="font-bold">
          أدراج المساعدين
        </h2>
      </template>
      <p v-if="!all.drawers.length" class="p-6 text-center text-sm text-(--ui-text-muted)">
        مفيش فلوس في أي درج.
      </p>
      <div v-for="d in all.drawers" :key="d.user_id" class="flex items-center gap-3 border-b border-(--ui-border) px-4 py-3 text-sm last:border-0">
        <span class="flex-1 font-bold">{{ d.name }}</span>
        <span class="num font-bold">{{ formatMoney(d.in_hand) }}</span>
        <UButton size="sm" icon="i-lucide-hand-coins" label="استلم" @click="receive(d)" />
      </div>
    </UCard>

    <UCard v-if="mine?.payments.length" :ui="{ body: 'p-0 sm:p-0' }">
      <template #header>
        <h2 class="font-bold">
          اللي حصّلته
        </h2>
      </template>
      <div v-for="p in mine.payments" :key="p.id" class="flex items-center gap-3 border-b border-(--ui-border) px-4 py-2.5 text-sm last:border-0">
        <span class="flex-1">{{ p.learner_name }} <span class="text-(--ui-text-muted)">· {{ p.kind === 'month' ? `شهر ${p.period}` : 'حصة' }}</span></span>
        <span class="num text-xs text-(--ui-text-muted)">{{ timeAgo(p.created_at) }}</span>
        <span class="num font-bold">{{ formatMoney(p.amount) }}</span>
      </div>
    </UCard>

    <UCard v-if="handovers.length" :ui="{ body: 'p-0 sm:p-0' }">
      <template #header>
        <h2 class="font-bold">
          آخر تسليمات
        </h2>
      </template>
      <div v-for="h in handovers" :key="h.id" class="flex flex-wrap items-center gap-3 border-b border-(--ui-border) px-4 py-2.5 text-sm last:border-0">
        <span class="flex-1">{{ h.user_name }} ← {{ h.received_by_name }}</span>
        <span class="num text-xs text-(--ui-text-muted)">{{ formatDate(h.created_at, true) }}</span>
        <span class="num">{{ formatMoney(h.counted) }}</span>
        <UBadge v-if="h.difference" :color="h.difference < 0 ? 'error' : 'warning'" variant="subtle" class="num" :label="`${h.difference > 0 ? '+' : ''}${formatMoney(h.difference)}`" />
      </div>
    </UCard>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ module: 'center', permission: 'center.door' })

type Handover = { id: string, user_name: string, expected: number, counted: number, difference: number, note: string | null, received_by_name: string | null, created_at: string }

const api = useApi()
const toast = useToast()
const store = useSessionStore()
const { data: mine, refresh: refreshMine } = await useAsyncData('center-cash', async () => (await api<{ data: { in_hand: number, payments: { id: string, learner_name: string | null, kind: string, period: string | null, amount: number, created_at: string }[], handovers: Handover[] } }>('/center/cash')).data)
const { data: all, refresh: refreshAll } = await useAsyncData('center-cash-all', async () => store.can('center.cash') ? (await api<{ data: { drawers: { user_id: string, name: string, in_hand: number, payments: number }[], handovers: Handover[] } }>('/center/cash/all')).data : null)
const handovers = computed(() => all.value?.handovers ?? mine.value?.handovers ?? [])

async function receive(d: { user_id: string, name: string, in_hand: number }) {
  const typed = prompt(`عدّيت كام من درج ${d.name}؟ (المفروض ${d.in_hand / 100} ج)`, String(d.in_hand / 100))
  if (typed === null) return
  try {
    const res = await api<{ data: Handover }>('/center/handovers', { method: 'POST', body: { user_id: d.user_id, counted: Math.round(Number(typed) * 100) } })
    toast.add({ color: res.data.difference ? 'warning' : 'success', title: res.data.difference ? `اتسلّم بفرق ${formatMoney(res.data.difference)}` : 'اتسلّم مظبوط' })
    await Promise.all([refreshAll(), refreshMine()])
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
}
</script>
