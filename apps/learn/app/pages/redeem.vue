<template>
  <div class="mx-auto max-w-md space-y-4">
    <UCard>
      <template #header>
        <h1 class="text-xl font-bold">
          عندي كود
        </h1>
        <p class="text-sm text-(--ui-text-muted)">
          اكتب الكود اللي على الكارت (12 حرف ورقم).
        </p>
      </template>
      <form class="space-y-4" @submit.prevent="redeem">
        <UInput v-model="code" dir="ltr" size="xl" class="w-full" placeholder="XXXX-XXXX-XXXX" maxlength="14" autocomplete="off" :ui="{ base: 'num text-center text-xl tracking-[0.2em] uppercase' }" @update:model-value="format" />
        <UButton type="submit" block size="lg" :loading="loading" :disabled="code.replace(/-/g, '').length !== 12" label="فعّل الكود" />
      </form>
      <UAlert v-if="error" class="mt-4" color="error" variant="subtle" :title="error" />
    </UCard>

    <UCard v-if="result" :ui="{ body: 'space-y-3 text-center' }">
      <UIcon name="i-lucide-party-popper" class="mx-auto size-12 text-primary" />
      <template v-if="result.kind === 'course'">
        <p class="text-lg font-bold">
          اتفتح لك «{{ result.course?.title }}»
        </p>
        <p v-if="result.enrollment?.ends_at" class="text-sm text-(--ui-text-muted)">
          لحد {{ formatDate(result.enrollment.ends_at) }}
        </p>
        <UButton :to="`/courses/${result.course?.id}`" block size="lg" label="ابدأ الكورس" />
      </template>
      <template v-else>
        <p class="text-lg font-bold">
          اتضاف {{ formatMoney(result.amount ?? 0) }} لمحفظتك
        </p>
        <p class="text-sm text-(--ui-text-muted)">
          رصيدك دلوقتي {{ formatMoney(result.balance ?? 0) }}
        </p>
        <UButton to="/" block size="lg" label="اختار كورس" />
      </template>
    </UCard>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ auth: true })

const api = useLearnApi()
const route = useRoute()
const code = ref('')
const loading = ref(false)
const error = ref<string | null>(null)
const result = ref<{ kind: 'course' | 'balance', course?: { id: string, title: string }, enrollment?: { ends_at: string | null }, amount?: number, balance?: number } | null>(null)

/** ABCD1234EFGH → ABCD-1234-EFGH as they type. */
function format(value: string | number) {
  const clean = String(value).toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 12)
  code.value = clean.match(/.{1,4}/g)?.join('-') ?? ''
}
if (typeof route.query.code === 'string') format(route.query.code)

async function redeem() {
  loading.value = true
  error.value = null
  result.value = null
  try {
    result.value = (await api<{ data: NonNullable<typeof result.value> }>('/learner/codes/redeem', { method: 'POST', body: { code: code.value } })).data
    code.value = ''
  }
  catch (e) {
    error.value = apiMessage(e)
  }
  finally {
    loading.value = false
  }
}
</script>
