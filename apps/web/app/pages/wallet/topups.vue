<template>
  <div class="max-w-5xl space-y-6">
    <PageHeader title="تحويلات الشحن" description="الطالب بيحوّل InstaPay أو محفظة ويرفع صورة التحويل. أكّد المبلغ اللي وصلك فعلاً، والرصيد ينزل في محفظته.">
      <UButton v-if="store.can('wallet.settings')" color="neutral" variant="outline" icon="i-lucide-settings" label="أرقام التحويل" @click="settingsOpen = true" />
    </PageHeader>

    <UAlert v-if="settings && !settings.transfers_enabled && store.can('wallet.settings')" color="neutral" variant="subtle" icon="i-lucide-info" title="الشحن بالتحويل مقفول" description="افتحه واكتب حساب InstaPay أو رقم المحفظة، عشان الطلاب يقدروا يشحنوا من المنصة." :actions="[{ label: 'افتحه', onClick: () => { settingsOpen = true } }]" />

    <div class="flex gap-1 rounded-(--ui-radius) bg-(--ui-bg-elevated) p-1 w-fit">
      <UButton v-for="s in statuses" :key="s.value" size="sm" :color="status === s.value ? 'primary' : 'neutral'" :variant="status === s.value ? 'solid' : 'ghost'" @click="status = s.value">
        {{ s.label }}
        <UBadge v-if="s.value === 'pending' && data?.meta.pending" size="sm" color="warning" class="num" :label="String(data.meta.pending)" />
      </UButton>
    </div>

    <p v-if="!rows.length" class="app-card p-8 text-center text-sm text-(--ui-text-muted)">
      {{ status === 'pending' ? 'مفيش تحويلات مستنية.' : 'مفيش.' }}
    </p>
    <div class="grid gap-3 md:grid-cols-2">
      <div v-for="t in rows" :key="t.id" class="app-card flex gap-3 p-3">
        <button v-if="t.has_proof" type="button" class="h-28 w-20 shrink-0 overflow-hidden rounded-lg bg-(--ui-bg-elevated)" aria-label="صورة التحويل" @click="proof = t.id">
          <img :src="proofUrls[t.id]" alt="" class="h-full w-full object-cover" loading="lazy">
        </button>
        <div class="min-w-0 flex-1 space-y-1 text-sm">
          <div class="flex items-center justify-between gap-2">
            <p class="truncate font-bold">
              {{ t.learner_name }}
            </p>
            <p class="num text-lg font-extrabold">
              {{ formatMoney(t.approved_amount ?? t.amount) }}
            </p>
          </div>
          <p class="text-(--ui-text-muted)">
            {{ METHODS[t.method] }}<template v-if="t.reference">
              · <span class="num" dir="ltr">{{ t.reference }}</span>
            </template>
          </p>
          <p class="text-xs text-(--ui-text-muted)">
            <span class="num">{{ localPhone(t.learner_phone) }}</span> · {{ timeAgo(t.created_at) }}
          </p>
          <p v-if="t.status === 'approved' && t.approved_amount !== t.amount" class="text-xs text-(--ui-warning)">
            قال {{ formatMoney(t.amount) }} ووصل {{ formatMoney(t.approved_amount) }}
          </p>
          <p v-if="t.status === 'rejected'" class="text-xs text-(--ui-error)">
            اترفض: {{ t.reason }}
          </p>
          <p v-if="t.decided_by_name" class="text-xs text-(--ui-text-muted)">
            {{ t.decided_by_name }} · {{ formatDate(t.decided_at, true) }}
          </p>
          <div v-if="t.status === 'pending'" class="flex flex-wrap gap-2 pt-1">
            <UButton size="sm" color="success" icon="i-lucide-check" label="وصل" :loading="busy === t.id" @click="approve(t)" />
            <UButton size="sm" color="neutral" variant="outline" label="وصل مبلغ تاني" @click="approve(t, true)" />
            <UButton size="sm" color="error" variant="ghost" label="ما وصلش" @click="reject(t)" />
          </div>
        </div>
      </div>
    </div>

    <UModal :open="!!proof" title="صورة التحويل" @update:open="v => !v && (proof = null)">
      <template #body>
        <img v-if="proof" :src="proofUrls[proof]" alt="صورة التحويل" class="mx-auto max-h-[70vh] rounded-lg">
      </template>
    </UModal>

    <UModal v-model:open="settingsOpen" title="الشحن بالتحويل" description="اللي الطالب هيشوفه لما يدوس «اشحن محفظتك».">
      <template #body>
        <form v-if="settingsForm" class="space-y-4" @submit.prevent="saveSettings">
          <USwitch v-model="settingsForm.transfers_enabled" label="الطلاب يقدروا يشحنوا بالتحويل" />
          <UFormField label="حساب InstaPay" hint="اختياري">
            <UInput v-model="settingsForm.instapay" dir="ltr" class="w-full" placeholder="mr.mohamed@instapay" />
          </UFormField>
          <UFormField label="رقم المحفظة (فودافون كاش وغيره)" hint="اختياري">
            <UInput v-model="settingsForm.wallet_number" dir="ltr" inputmode="tel" class="w-full" placeholder="01xxxxxxxxx" />
          </UFormField>
          <UFormField label="تعليمات" hint="اختياري">
            <UTextarea v-model="settingsForm.instructions" :rows="2" class="w-full" placeholder="اكتب اسمك في ملاحظة التحويل" />
          </UFormField>
          <UFormField label="أقل مبلغ (ج)">
            <UInputNumber v-model="settingsForm.min_pounds" :min="1" class="w-full" />
          </UFormField>
          <UAlert v-if="settingsError" color="error" variant="subtle" :title="settingsError" />
          <div class="flex justify-end gap-2">
            <UButton color="neutral" variant="ghost" label="إلغاء" @click="settingsOpen = false" />
            <UButton type="submit" label="حفظ" />
          </div>
        </form>
      </template>
    </UModal>
  </div>
</template>

<script setup lang="ts">
import type { WalletSettings, WalletTopup } from '~/types/manhagy'

definePageMeta({ permission: 'wallet.confirm' })

const METHODS: Record<WalletTopup['method'], string> = { instapay: 'InstaPay', wallet: 'محفظة موبايل', other: 'تحويل' }
const statuses = [{ value: 'pending', label: 'مستنية' }, { value: 'approved', label: 'اتأكدت' }, { value: 'rejected', label: 'اترفضت' }] as const

const api = useApi()
const store = useSessionStore()
const toast = useToast()
const status = ref<'pending' | 'approved' | 'rejected'>('pending')
const { data, refresh } = await useAsyncData('topups', () => api<{ data: WalletTopup[], meta: { pending: number } }>('/wallet/topups', { query: { status: status.value } }), { watch: [status] })
const rows = computed(() => data.value?.data ?? [])
const busy = ref<string | null>(null)
const proof = ref<string | null>(null)

// The proofs need the bearer token: fetched as blobs.
const proofUrls = reactive<Record<string, string>>({})
watch(rows, async (list) => {
  for (const t of list.filter(t => t.has_proof && !proofUrls[t.id])) {
    const blob = await api<Blob>(`/wallet/topups/${t.id}/proof`, { responseType: 'blob' }).catch(() => null)
    if (blob) proofUrls[t.id] = URL.createObjectURL(blob)
  }
}, { immediate: true })
onBeforeUnmount(() => Object.values(proofUrls).forEach(URL.revokeObjectURL))

async function approve(t: WalletTopup, other = false) {
  let amount: number | undefined
  if (other) {
    const typed = prompt('وصلك كام جنيه؟', String(t.amount / 100))
    if (!typed) return
    amount = Math.round(Number(typed) * 100)
    if (!amount) return
  }
  busy.value = t.id
  try {
    await api(`/wallet/topups/${t.id}/approve`, { method: 'POST', body: { amount } })
    toast.add({ color: 'success', title: `اتشحن ${formatMoney(amount ?? t.amount)} لـ ${t.learner_name}` })
    await refresh()
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    busy.value = null
  }
}
async function reject(t: WalletTopup) {
  const reason = prompt('ليه؟ (الطالب هيشوف السبب)', 'التحويل ما وصلش')
  if (!reason?.trim()) return
  try {
    await api(`/wallet/topups/${t.id}/reject`, { method: 'POST', body: { reason } })
    await refresh()
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
}

const settingsOpen = ref(false)
const settingsError = ref<string | null>(null)
const { data: settings, refresh: refreshSettings } = await useAsyncData('wallet-settings', async () => store.can('wallet.settings') ? (await api<{ data: WalletSettings }>('/wallet/settings')).data : null)
const settingsForm = ref<{ transfers_enabled: boolean, instapay: string, wallet_number: string, instructions: string, min_pounds: number } | null>(null)
watch(settingsOpen, (open) => {
  if (open && settings.value) {
    const s = settings.value
    settingsForm.value = { transfers_enabled: s.transfers_enabled, instapay: s.instapay ?? '', wallet_number: s.wallet_number ?? '', instructions: s.instructions ?? '', min_pounds: s.min_topup / 100 }
    settingsError.value = null
  }
})
async function saveSettings() {
  if (!settingsForm.value) return
  const { min_pounds, ...rest } = settingsForm.value
  try {
    await api('/wallet/settings', { method: 'PUT', body: { ...rest, instapay: rest.instapay || null, wallet_number: rest.wallet_number || null, instructions: rest.instructions || null, min_topup: Math.round(min_pounds * 100) } })
    settingsOpen.value = false
    await refreshSettings()
  }
  catch (e) {
    settingsError.value = apiErrorMessage(e)
  }
}
</script>
