<template>
  <USkeleton v-if="!signup" class="h-96 w-full" />

  <!-- Self sign-up closed by the platform: the team opens the account, on WhatsApp. -->
  <UCard v-else-if="!signup.open">
    <template #header>
      <h1 class="text-xl font-bold">
        افتح منصتك
      </h1>
    </template>
    <div class="space-y-5 text-center">
      <span class="mx-auto flex size-16 items-center justify-center rounded-full bg-(--app-primary-soft)">
        <UIcon name="i-lucide-message-circle" class="size-8 text-(--ui-primary)" />
      </span>
      <p class="leading-loose">
        {{ signup.message }}
      </p>
      <UButton v-if="whatsappHref" :to="whatsappHref" target="_blank" rel="noopener" block size="xl" color="success" icon="i-lucide-message-circle" label="كلّمنا واتساب" @click="reportContact(apiBase)" />
      <p v-if="signup.whatsapp" class="text-sm text-(--ui-text-muted)">
        أو كلّمنا على <bdi class="num font-bold">{{ localPhone(signup.whatsapp) }}</bdi>
      </p>
    </div>
    <template #footer>
      <p class="text-sm text-center text-(--ui-text-muted)">
        عندك حساب؟
        <NuxtLink to="/login" class="text-primary font-semibold">
          سجّل دخول
        </NuxtLink>
      </p>
    </template>
  </UCard>

  <UCard v-else>
    <template #header>
      <h1 class="text-xl font-bold">
        افتح منصتك
      </h1>
      <p class="text-sm text-(--ui-text-muted)">
        منصة باسمك لطلابك: فيديو محمي، أكواد، امتحانات، وتقرير لولي الأمر. 14 يوم تجربة مجانية.
      </p>
    </template>

    <form class="space-y-4" @submit.prevent="submit">
      <div class="grid grid-cols-2 gap-2" role="radiogroup" aria-label="إنت">
        <button
          v-for="k in kinds"
          :key="k.value"
          type="button"
          role="radio"
          :aria-checked="form.shop_types[0] === k.value"
          class="flex items-center gap-3 rounded-(--ui-radius) border p-3 text-start transition"
          :class="form.shop_types[0] === k.value ? 'border-primary bg-(--app-primary-soft)' : 'border-(--ui-border) hover:border-(--ui-border-accented)'"
          @click="form.shop_types = [k.value]"
        >
          <UIcon :name="k.icon" class="size-6 shrink-0 text-(--ui-primary)" />
          <span>
            <span class="block font-bold">{{ k.label }}</span>
            <span class="block text-xs text-(--ui-text-muted)">{{ k.hint }}</span>
          </span>
        </button>
      </div>

      <UFormField :label="form.shop_types[0] === 'center' ? 'اسم السنتر' : 'اسم المنصة'" :description="form.shop_types[0] === 'center' ? undefined : 'زي ما الطلاب يعرفوك: أ. محمد سامي — فيزياء'">
        <UInput v-model="form.shop_name" class="w-full" />
      </UFormField>

      <UFormField label="اسمك">
        <UInput v-model="form.owner_name" class="w-full" />
      </UFormField>
      <UFormField label="رقم الموبايل">
        <UInput v-model="form.phone" dir="ltr" inputmode="tel" placeholder="01xxxxxxxxx" class="w-full" />
      </UFormField>
      <div class="grid grid-cols-2 gap-3">
        <UFormField label="كلمة السر">
          <UInput v-model="form.password" type="password" class="w-full" />
        </UFormField>
        <UFormField label="تأكيدها">
          <UInput v-model="form.password_confirmation" type="password" class="w-full" />
        </UFormField>
      </div>

      <UFormField label="كود الدعوة" hint="اختياري" :description="form.referral_code ? 'هتاخد خصم على أول شهور من اشتراكك.' : 'لو مدرس صاحبك بعتلك كود.'">
        <UInput v-model="form.referral_code" dir="ltr" class="w-full" maxlength="12" placeholder="ABC123" @update:model-value="v => form.referral_code = String(v).toUpperCase()" />
      </UFormField>

      <UAlert v-if="error" color="error" variant="subtle" :title="error" />

      <UButton type="submit" block size="lg" :loading="loading" label="ابدأ التجربة" />
    </form>

    <template #footer>
      <p class="text-sm text-center text-(--ui-text-muted)">
        عندك حساب؟
        <NuxtLink to="/login" class="text-primary font-semibold">
          سجّل دخول
        </NuxtLink>
      </p>
      <p class="mt-2 text-xs text-center text-(--ui-text-muted)">
        بياناتك وبيانات طلابك في أمان —
        <a :href="`${siteUrl}/privacy`" target="_blank" class="text-primary hover:underline">سياسة الخصوصية</a>
      </p>
    </template>
  </UCard>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'auth', guest: true, marketing: true })

const store = useSessionStore()
const route = useRoute()
const api = useApi()
const apiBase = String(useRuntimeConfig().public.apiBase)

// Whether the platform takes sign-ups now; else where to ask for an account. On failure, show the form
// (the API still refuses a closed sign-up).
const signup = ref<{ open: boolean, whatsapp: string | null, message: string } | null>(null)
onMounted(async () => {
  signup.value = await api<{ data: NonNullable<typeof signup.value> }>('/public/signup').then(r => r.data).catch(() => ({ open: true, whatsapp: null, message: '' }))
})
const whatsappHref = computed(() => {
  const number = signup.value?.whatsapp?.replace(/\D/g, '')
  if (!number) return null
  // What the team needs to open the account the same way the form would have.
  const extra = [
    form.referral_code && `كود دعوة: ${form.referral_code}`,
    acquisition()?.source && `جاي من: ${acquisition()!.source}`,
  ].filter(Boolean)
  const text = `السلام عليكم، عايز أفتح منصتي على منهجي.${extra.length ? `\n(${extra.join(' · ')})` : ''}`
  return `https://wa.me/${number}?text=${encodeURIComponent(text)}`
})
const siteUrl = String(useRuntimeConfig().public.siteUrl).replace(/\/$/, '')

const form = reactive({
  shop_name: '',
  shop_types: ['teacher'] as string[],
  owner_name: '',
  phone: '',
  password: '',
  password_confirmation: '',
  // From an invite link: /register?ref=CODE
  referral_code: typeof route.query.ref === 'string' ? route.query.ref.trim().toUpperCase().slice(0, 12) : '',
})
const loading = ref(false)
const error = ref<string | null>(null)
const kinds = [
  { value: 'teacher', label: 'مدرس', hint: 'منصة باسمك لطلابك', icon: 'i-lucide-presentation' },
  { value: 'center', label: 'سنتر', hint: 'أكتر من مدرس ومجموعات', icon: 'i-lucide-school' },
]

// The campaign the owner came from (utm_* from the website or an ad link), kept on the new shop.
function acquisition(): Record<string, string> | undefined {
  const out: Record<string, string> = {}
  for (const k of ['source', 'medium', 'campaign', 'content', 'term'] as const) {
    const v = route.query[`utm_${k}`]
    if (typeof v === 'string' && v.trim()) out[k] = v.trim().slice(0, 80)
  }
  return Object.keys(out).length ? out : undefined
}

async function submit() {
  loading.value = true
  error.value = null
  try {
    const eventId = await store.register({ ...form, referral_code: form.referral_code.trim() || undefined, acquisition: acquisition() })
    reportSignup(eventId)
    await navigateTo('/')
  }
  catch (e) {
    error.value = apiErrorMessage(e)
  }
  finally {
    loading.value = false
  }
}
</script>
