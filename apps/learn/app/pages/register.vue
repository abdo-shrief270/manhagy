<template>
  <div class="mx-auto max-w-md">
    <UCard v-if="site && !site.registration_open">
      <p class="text-center">
        التسجيل مقفول دلوقتي على المنصة دي. كلّم المدرس.
      </p>
    </UCard>
    <UCard v-else>
      <template #header>
        <h1 class="text-xl font-bold">
          حساب جديد
        </h1>
        <p class="text-sm text-(--ui-text-muted)">
          على منصة {{ site?.title }}
        </p>
      </template>
      <form class="space-y-4" @submit.prevent="submit">
        <UFormField label="اسمك بالكامل" :error="errors.name">
          <UInput v-model="form.name" class="w-full" size="lg" />
        </UFormField>
        <UFormField label="رقم موبايلك" :error="errors.phone" description="هتأكده برسالة واتساب من نفس الرقم.">
          <UInput v-model="form.phone" dir="ltr" inputmode="tel" placeholder="01xxxxxxxxx" class="w-full" size="lg" />
        </UFormField>
        <UFormField label="الصف" :error="errors.grade_id">
          <GradePicker v-model="form.grade_id" />
        </UFormField>
        <UFormField label="رقم ولي الأمر" :hint="site?.require_parent_phone ? undefined : 'اختياري'" :error="errors.parent_phone" description="بيوصله تقرير بمستواك كل أسبوع.">
          <UInput v-model="form.parent_phone" dir="ltr" inputmode="tel" placeholder="01xxxxxxxxx" class="w-full" />
        </UFormField>
        <div class="grid grid-cols-2 gap-3">
          <UFormField label="كلمة السر" :error="errors.password">
            <UInput v-model="form.password" type="password" class="w-full" />
          </UFormField>
          <UFormField label="تأكيدها">
            <UInput v-model="form.password_confirmation" type="password" class="w-full" />
          </UFormField>
        </div>
        <UCheckbox v-model="form.consent" :label="`موافق إن ${site?.title ?? 'المدرس'} ومنهجي يحتفظوا ببياناتي عشان الكورسات والتقارير`" />
        <p v-if="errors.consent" class="text-sm text-(--ui-error)">
          {{ errors.consent }}
        </p>
        <UAlert v-if="error" color="error" variant="subtle" :title="error" />
        <UButton type="submit" block size="lg" :loading="loading" label="سجّل" />
      </form>
      <template #footer>
        <p class="text-center text-sm">
          عندك حساب على منهجي؟ <NuxtLink to="/login" class="text-primary">ادخل بيه</NuxtLink>
        </p>
      </template>
    </UCard>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ guest: true })

const { site } = useSite()
const learner = useLearner()
const form = reactive({ name: '', phone: '', grade_id: null as string | null, parent_phone: '', password: '', password_confirmation: '', consent: true })
const loading = ref(false)
const error = ref<string | null>(null)
const errors = ref<Record<string, string>>({})

async function submit() {
  loading.value = true
  error.value = null
  errors.value = {}
  try {
    await learner.register({ ...form, parent_phone: form.parent_phone || null })
    await navigateTo(learner.pendingVerification.value ? '/verify' : '/')
  }
  catch (e) {
    const fields = (e as { data?: { errors?: Record<string, string[]> } }).data?.errors
    errors.value = Object.fromEntries(Object.entries(fields ?? {}).map(([k, v]) => [k, v[0] ?? '']))
    error.value = fields ? null : apiMessage(e)
  }
  finally {
    loading.value = false
  }
}
</script>
