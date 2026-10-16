<template>
  <div class="mx-auto max-w-md">
    <UCard>
      <template #header>
        <h1 class="text-xl font-bold">
          نسيت كلمة السر
        </h1>
      </template>
      <form v-if="!verification" class="space-y-4" @submit.prevent="start">
        <UFormField label="رقم موبايلك">
          <UInput v-model="phone" dir="ltr" inputmode="tel" placeholder="01xxxxxxxxx" class="w-full" size="lg" autofocus />
        </UFormField>
        <UButton type="submit" block size="lg" :loading="loading" label="كمّل" />
      </form>
      <div v-else class="space-y-6">
        <WhatsappSend :verification="verification" />
        <form class="space-y-4 border-t border-(--ui-border) pt-4" @submit.prevent="reset">
          <p class="text-sm text-(--ui-text-muted)">
            بعد ما تبعت الرسالة، اكتب كلمة السر الجديدة:
          </p>
          <UFormField label="كلمة السر الجديدة">
            <UInput v-model="password" type="password" class="w-full" />
          </UFormField>
          <UFormField label="تأكيدها">
            <UInput v-model="confirmation" type="password" class="w-full" />
          </UFormField>
          <UButton type="submit" block size="lg" :loading="loading" label="غيّر كلمة السر" />
        </form>
      </div>
      <UAlert v-if="error" class="mt-4" color="error" variant="subtle" :title="error" />
    </UCard>
  </div>
</template>

<script setup lang="ts">
import type { Verification } from '~/utils/types'

definePageMeta({ guest: true })

const { slug } = useSite()
const api = useLearnApi()
const toast = useToast()
const phone = ref('')
const password = ref('')
const confirmation = ref('')
const loading = ref(false)
const error = ref<string | null>(null)
const verification = ref<Verification | null>(null)

async function start() {
  loading.value = true
  error.value = null
  try {
    verification.value = (await api<{ data: Verification }>(`/public/sites/${slug.value}/password/forgot`, { method: 'POST', body: { phone: phone.value } })).data
  }
  catch (e) {
    error.value = apiMessage(e)
  }
  finally {
    loading.value = false
  }
}

async function reset() {
  loading.value = true
  error.value = null
  try {
    await api(`/public/sites/${slug.value}/password/reset`, { method: 'POST', body: { phone: phone.value, code: verification.value!.code, password: password.value, password_confirmation: confirmation.value } })
    toast.add({ color: 'success', title: 'كلمة السر اتغيرت. ادخل بيها.' })
    await navigateTo('/login')
  }
  catch (e) {
    error.value = apiMessage(e)
  }
  finally {
    loading.value = false
  }
}
</script>
