<template>
  <div class="mx-auto max-w-sm">
    <UCard>
      <template #header>
        <h1 class="text-xl font-bold">
          دخول
        </h1>
        <p class="text-sm text-(--ui-text-muted)">
          {{ site?.title }}
        </p>
      </template>
      <form class="space-y-4" @submit.prevent="submit">
        <UFormField label="رقم موبايلك">
          <UInput v-model="phone" dir="ltr" inputmode="tel" placeholder="01xxxxxxxxx" class="w-full" size="lg" autofocus />
        </UFormField>
        <UFormField label="كلمة السر">
          <UInput v-model="password" type="password" class="w-full" size="lg" />
        </UFormField>
        <UAlert v-if="error" color="error" variant="subtle" :title="error" :description="code === 'device_limit' ? 'كلّم المدرس أو المساعد عشان يفكّ جهاز قديم من حسابك.' : undefined" />
        <UButton type="submit" block size="lg" :loading="loading" label="دخول" />
      </form>
      <template #footer>
        <div class="flex items-center justify-between text-sm">
          <NuxtLink to="/forgot" class="text-primary">نسيت كلمة السر؟</NuxtLink>
          <NuxtLink v-if="site?.registration_open" to="/register" class="text-primary">حساب جديد</NuxtLink>
        </div>
      </template>
    </UCard>
    <p class="mt-4 text-center text-xs text-(--ui-text-muted)">
      حساب واحد لكل المدرسين على منهجي: لو سجّلت عند مدرس تاني، ادخل بنفس الرقم وكلمة السر.
    </p>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ guest: true })

const { site } = useSite()
const learner = useLearner()
const route = useRoute()
const phone = ref('')
const password = ref('')
const loading = ref(false)
const error = ref<string | null>(null)
const code = ref<string | null>(null)

async function submit() {
  loading.value = true
  error.value = null
  try {
    await learner.login(phone.value, password.value)
    await navigateTo(typeof route.query.redirect === 'string' ? route.query.redirect : '/')
  }
  catch (e) {
    error.value = apiMessage(e)
    code.value = apiCode(e)
  }
  finally {
    loading.value = false
  }
}
</script>
