<template>
  <div class="mx-auto max-w-2xl space-y-4">
    <div class="app-card flex items-center justify-between p-5">
      <div>
        <p class="text-sm text-(--ui-text-muted)">
          رصيدك
        </p>
        <p class="num text-3xl font-extrabold">
          {{ formatMoney(wallet?.balance ?? 0) }}
        </p>
      </div>
      <div class="flex flex-col gap-2">
        <UButton to="/redeem" color="neutral" variant="outline" icon="i-lucide-ticket" label="اشحن بكود" />
        <UButton v-if="wallet?.transfer" icon="i-lucide-send" label="اشحن بتحويل" @click="topupOpen = true" />
      </div>
    </div>

    <UCard v-if="wallet?.topups.some(t => t.status === 'pending')" :ui="{ body: 'space-y-2' }">
      <p v-for="t in wallet.topups.filter(t => t.status === 'pending')" :key="t.id" class="flex items-center gap-2 text-sm">
        <UIcon name="i-lucide-hourglass" class="size-4 text-(--ui-warning)" />
        تحويل <span class="num font-bold">{{ formatMoney(t.amount) }}</span> بيتراجع…
      </p>
    </UCard>

    <UCard :ui="{ body: 'p-0 sm:p-0' }">
      <template #header>
        <h2 class="font-bold">
          الحركات
        </h2>
      </template>
      <p v-if="!wallet?.transactions.length" class="p-6 text-center text-sm text-(--ui-text-muted)">
        مفيش حركات لسه.
      </p>
      <div v-for="t in wallet?.transactions" :key="t.id" class="flex items-center gap-3 border-b border-(--ui-border) px-4 py-3 text-sm last:border-0">
        <span class="flex-1">{{ t.note || TYPES[t.type] }}</span>
        <span class="num text-xs text-(--ui-text-muted)">{{ formatDate(t.created_at) }}</span>
        <span class="num font-bold" :class="t.amount > 0 ? 'text-(--ui-success)' : ''">{{ t.amount > 0 ? '+' : '' }}{{ formatMoney(t.amount) }}</span>
      </div>
      <div v-for="t in wallet?.topups.filter(t => t.status === 'rejected')" :key="t.id" class="flex items-center gap-3 border-t border-(--ui-border) px-4 py-3 text-sm text-(--ui-error)">
        <span class="flex-1">تحويل <span class="num">{{ formatMoney(t.amount) }}</span> اترفض: {{ t.reason }}</span>
      </div>
    </UCard>

    <UModal v-model:open="topupOpen" title="اشحن بتحويل">
      <template #body>
        <form v-if="wallet?.transfer" class="space-y-4" @submit.prevent="send">
          <div class="space-y-1 rounded-(--ui-radius) bg-(--ui-bg-elevated) p-3 text-sm">
            <p v-if="wallet.transfer.instapay">
              InstaPay: <b class="num" dir="ltr">{{ wallet.transfer.instapay }}</b>
            </p>
            <p v-if="wallet.transfer.wallet_number">
              محفظة: <b class="num">{{ wallet.transfer.wallet_number }}</b>
            </p>
            <p v-if="wallet.transfer.instructions" class="text-(--ui-text-muted)">
              {{ wallet.transfer.instructions }}
            </p>
          </div>
          <UFormField label="حوّلت كام؟ (ج)" :description="`أقل مبلغ ${formatMoney(wallet.transfer.min_topup)}`">
            <UInputNumber v-model="form.pounds" :min="wallet.transfer.min_topup / 100" class="w-full" />
          </UFormField>
          <UFormField label="حوّلت بإيه؟">
            <USelect v-model="form.method" :items="[{ value: 'instapay', label: 'InstaPay' }, { value: 'wallet', label: 'محفظة موبايل' }, { value: 'other', label: 'غير كده' }]" class="w-full" />
          </UFormField>
          <UFormField label="رقم أو اسم اللي حوّل منه" hint="اختياري">
            <UInput v-model="form.reference" dir="ltr" class="w-full" />
          </UFormField>
          <UFormField label="صورة التحويل">
            <input type="file" accept="image/*" class="block w-full text-sm" @change="e => proof = (e.target as HTMLInputElement).files?.[0] ?? null">
          </UFormField>
          <UAlert v-if="error" color="error" variant="subtle" :title="error" />
          <UButton type="submit" block :disabled="!proof || !form.pounds" :loading="sending" label="ابعت للمراجعة" />
        </form>
      </template>
    </UModal>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ auth: true })

type Wallet = {
  balance: number
  transactions: { id: string, type: 'code' | 'transfer' | 'purchase' | 'adjustment', amount: number, note: string | null, created_at: string }[]
  topups: { id: string, amount: number, status: string, reason: string | null }[]
  transfer: { instapay: string | null, wallet_number: string | null, instructions: string | null, min_topup: number } | null
}
const TYPES = { code: 'شحن بكود', transfer: 'شحن بتحويل', purchase: 'شراء', adjustment: 'تعديل من المدرس' }

const api = useLearnApi()
const toast = useToast()
const { data: wallet, refresh } = await useAsyncData('wallet', async () => (await api<{ data: Wallet }>('/learner/wallet')).data)

const topupOpen = ref(false)
const form = reactive({ pounds: 0, method: 'instapay', reference: '' })
const proof = ref<File | null>(null)
const sending = ref(false)
const error = ref<string | null>(null)

/** Phones take big photos: shrink to ~1600px JPEG before sending. */
async function shrink(file: File): Promise<Blob> {
  const bitmap = await createImageBitmap(file).catch(() => null)
  if (!bitmap) return file
  const scale = Math.min(1, 1600 / Math.max(bitmap.width, bitmap.height))
  const canvas = document.createElement('canvas')
  canvas.width = Math.round(bitmap.width * scale)
  canvas.height = Math.round(bitmap.height * scale)
  canvas.getContext('2d')!.drawImage(bitmap, 0, 0, canvas.width, canvas.height)
  return await new Promise(resolve => canvas.toBlob(b => resolve(b ?? file), 'image/jpeg', 0.85))
}

async function send() {
  if (!proof.value) return
  sending.value = true
  error.value = null
  try {
    const body = new FormData()
    body.append('amount', String(Math.round(form.pounds * 100)))
    body.append('method', form.method)
    if (form.reference) body.append('reference', form.reference)
    body.append('proof', await shrink(proof.value), 'proof.jpg')
    await api('/learner/wallet/topups', { method: 'POST', body })
    topupOpen.value = false
    toast.add({ color: 'success', title: 'اتبعت. هيوصلك الرصيد أول ما يتراجع.' })
    await refresh()
  }
  catch (e) {
    error.value = apiMessage(e)
  }
  finally {
    sending.value = false
  }
}
</script>
