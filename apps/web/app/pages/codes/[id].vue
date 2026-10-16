<template>
  <div class="max-w-4xl space-y-6">
    <USkeleton v-if="!batch" class="h-64" />
    <template v-else>
      <PageHeader :title="`الدفعة ${batch.number}`" :description="batch.kind === 'course' ? `كورس «${batch.course_title}»${batch.days ? ` · ${accessLabel(batch.days)}` : ''}` : `رصيد ${formatMoney(batch.value)}`">
        <UButton color="neutral" variant="outline" icon="i-lucide-arrow-right" label="الأكواد" to="/codes" />
        <UButton v-if="batch.status === 'active' && store.can('codes.manage')" color="error" variant="soft" icon="i-lucide-ban" label="إلغاء" @click="cancelOpen = true" />
      </PageHeader>

      <UAlert v-if="batch.status === 'cancelled'" color="error" variant="subtle" icon="i-lucide-ban" :title="`الدفعة اتلغت: ${batch.cancelled_reason}`" />

      <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <StatTile label="الأكواد" :value="String(batch.count)" icon="i-lucide-ticket" />
        <StatTile label="اتستخدم" :value="String(batch.stats.used)" icon="i-lucide-circle-check" />
        <StatTile label="لسه" :value="String(batch.stats.unused)" icon="i-lucide-circle-dashed" />
        <StatTile label="اتلغى" :value="String(batch.stats.cancelled)" icon="i-lucide-ban" />
      </div>

      <UCard :ui="{ body: 'space-y-1 text-sm' }">
        <p>سعر الكارت: <span class="num font-bold">{{ formatMoney(batch.unit_price) }}</span></p>
        <p v-if="batch.distributor_name">
          الموزّع: <NuxtLink :to="`/codes/distributors/${batch.distributor_id}`" class="font-bold text-primary">{{ batch.distributor_name }}</NuxtLink>
        </p>
        <p v-if="batch.expires_at">
          صالحة لحد <span class="num">{{ formatDate(batch.expires_at) }}</span>
        </p>
        <p class="text-(--ui-text-muted)">
          اتعملت {{ formatDate(batch.created_at, true) }}<template v-if="batch.created_by_name"> · {{ batch.created_by_name }}</template>
        </p>
      </UCard>

      <UCard :ui="{ body: 'p-0 sm:p-0' }">
        <template #header>
          <h2 class="font-bold">
            اللي اتستخدم
          </h2>
        </template>
        <p v-if="!batch.used?.length" class="p-6 text-center text-sm text-(--ui-text-muted)">
          لسه محدش استخدم كود من الدفعة دي.
        </p>
        <div v-else class="divide-y divide-(--ui-border)">
          <div v-for="u in batch.used" :key="u.serial" class="flex items-center gap-3 px-4 py-2.5 text-sm">
            <span class="num w-12 text-(--ui-text-muted)">#{{ u.serial }}</span>
            <span class="flex-1 font-bold">{{ u.learner_name ?? '—' }}</span>
            <span class="num text-(--ui-text-muted)">{{ formatDate(u.used_at, true) }}</span>
          </div>
        </div>
      </UCard>
      <p v-if="batch.cancelled_serials?.length" class="text-sm text-(--ui-text-muted)">
        الكروت الملغية: <span class="num">{{ batch.cancelled_serials.join('، ') }}</span>
      </p>
    </template>

    <UModal v-model:open="cancelOpen" title="إلغاء أكواد" description="الأكواد اللي لسه ما اتستخدمتش هتبطّل تشتغل. اللي اتستخدم يفضل زي ما هو.">
      <template #body>
        <form class="space-y-4" @submit.prevent="cancel">
          <div class="flex gap-1 rounded-(--ui-radius) bg-(--ui-bg-elevated) p-1">
            <UButton class="flex-1 justify-center" :color="whole ? 'primary' : 'neutral'" :variant="whole ? 'solid' : 'ghost'" label="الدفعة كلها" @click="whole = true" />
            <UButton class="flex-1 justify-center" :color="!whole ? 'primary' : 'neutral'" :variant="!whole ? 'solid' : 'ghost'" label="كروت معيّنة" @click="whole = false" />
          </div>
          <UFormField v-if="!whole" label="أرقام الكروت" description="مثلاً: 12، 15، 30-45">
            <UInput v-model="serials" dir="ltr" class="w-full" />
          </UFormField>
          <UFormField label="السبب">
            <UInput v-model="reason" class="w-full" placeholder="الكروت ضاعت / اتسرقت" />
          </UFormField>
          <div class="flex justify-end gap-2">
            <UButton color="neutral" variant="ghost" label="رجوع" @click="cancelOpen = false" />
            <UButton type="submit" color="error" :disabled="!reason.trim()" :loading="cancelling" label="الغي" />
          </div>
        </form>
      </template>
    </UModal>
  </div>
</template>

<script setup lang="ts">
import type { CodeBatch } from '~/types/manhagy'

definePageMeta({ permission: 'codes.view' })

const api = useApi()
const toast = useToast()
const store = useSessionStore()
const id = String(useRoute().params.id)
const { data: batch, refresh } = await useAsyncData(`batch-${id}`, async () => (await api<{ data: CodeBatch }>(`/codes/batches/${id}`)).data)

const cancelOpen = ref(false)
const whole = ref(true)
const serials = ref('')
const reason = ref('')
const cancelling = ref(false)

/** «12، 15، 30-45» → [12, 15, 30, …, 45] */
function parseSerials(text: string): number[] {
  return [...new Set(text.split(/[,،\s]+/).filter(Boolean).flatMap((part) => {
    const [a, b] = part.split('-').map(Number)
    return b && a ? Array.from({ length: Math.max(0, b - a + 1) }, (_, i) => a + i) : a ? [a] : []
  }))]
}

async function cancel() {
  cancelling.value = true
  try {
    const res = await api<{ data: CodeBatch & { just_cancelled: number } }>(`/codes/batches/${id}/cancel`, { method: 'POST', body: { reason: reason.value, serials: whole.value ? undefined : parseSerials(serials.value) } })
    toast.add({ color: 'success', title: `اتلغى ${res.data.just_cancelled} كود` })
    cancelOpen.value = false
    await refresh()
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    cancelling.value = false
  }
}
</script>
