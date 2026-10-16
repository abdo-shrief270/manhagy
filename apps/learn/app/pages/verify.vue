<template>
  <div class="mx-auto max-w-md">
    <UCard>
      <template #header>
        <h1 class="text-xl font-bold">
          أكّد رقمك
        </h1>
      </template>
      <div v-if="done" class="space-y-4 text-center">
        <UIcon name="i-lucide-circle-check" class="mx-auto size-14 text-(--ui-success)" />
        <p class="text-lg font-bold">
          رقمك اتأكد 🎉
        </p>
        <UButton to="/" block size="lg" label="يلا نبدأ" />
      </div>
      <WhatsappSend v-else-if="verification" :verification="verification" />
      <USkeleton v-else class="h-40" />
      <UAlert v-if="error" class="mt-4" color="error" variant="subtle" :title="error" />
    </UCard>
  </div>
</template>

<script setup lang="ts">
import type { Verification } from '~/utils/types'

definePageMeta({ auth: true })

const learner = useLearner()
const api = useLearnApi()
const verification = ref<Verification | null>(learner.pendingVerification.value)
const done = ref(learner.me.value?.phone_verified ?? false)
const error = ref<string | null>(null)

onMounted(async () => {
  if (done.value) return
  if (!verification.value) {
    try {
      verification.value = (await api<{ data: Verification }>('/learner/phone/verify', { method: 'POST' })).data
    }
    catch (e) {
      if (apiCode(e) === 'phone_verified' || apiCode(e) === 'verification_off') done.value = true
      else error.value = apiMessage(e)
    }
  }
})

// Poll while the student is away in WhatsApp (and right when they come back).
let timer: ReturnType<typeof setInterval> | undefined
async function check() {
  if (done.value) return
  const res = await api<{ data: { verified: boolean } }>('/learner/phone/verify').catch(() => null)
  if (res?.data.verified) {
    done.value = true
    learner.pendingVerification.value = null
    await learner.load(true)
  }
}
onMounted(() => {
  timer = setInterval(check, 3000)
  window.addEventListener('focus', check)
})
onBeforeUnmount(() => {
  clearInterval(timer)
  window.removeEventListener('focus', check)
})
</script>
