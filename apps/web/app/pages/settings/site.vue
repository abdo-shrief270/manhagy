<template>
  <div class="max-w-3xl space-y-6">
    <PageHeader title="منصتك" description="المكان اللي طلابك بيدخلوا منه: اللينك والاسم والشكل، وعدد الأجهزة لكل طالب." />

    <USkeleton v-if="!form" class="h-96" />
    <form v-else class="space-y-6" @submit.prevent="save">
      <UCard>
        <template #header>
          <h2 class="font-bold">
            اللينك
          </h2>
        </template>
        <UFormField label="عنوان منصتك" :error="errors.slug" description="حروف إنجليزي صغيرة وأرقام وشرطة. ابعته لطلابك في الجروب.">
          <UInputGroup class="w-full" dir="ltr">
            <UInput v-model="form.slug" class="flex-1" :ui="{ base: 'num' }" placeholder="mr-mohamed" />
            <UBadge color="neutral" variant="outline" size="lg" :label="`.${host}`" />
          </UInputGroup>
        </UFormField>
        <div v-if="site?.url" class="mt-3 flex flex-wrap items-center gap-2 rounded-(--ui-radius) bg-(--ui-bg-elevated) p-3 text-sm">
          <UIcon name="i-lucide-globe" class="size-4 text-(--ui-primary)" />
          <a :href="site.url" target="_blank" class="num font-bold text-primary" dir="ltr">{{ site.url }}</a>
          <UButton class="ms-auto" size="xs" color="neutral" variant="outline" :icon="copied ? 'i-lucide-check' : 'i-lucide-copy'" label="نسخ" @click="copy" />
          <UButton size="xs" color="success" variant="soft" icon="i-lucide-message-circle" label="ابعته واتساب" :to="shareLink" target="_blank" />
        </div>
      </UCard>

      <UCard :ui="{ body: 'space-y-4' }">
        <template #header>
          <h2 class="font-bold">
            الشكل
          </h2>
        </template>
        <UFormField label="اسم المنصة" :error="errors.title" description="بيظهر فوق في المنصة وفي رسايل ولي الأمر.">
          <UInput v-model="form.title" class="w-full" placeholder="أ. محمد سامي — فيزياء" />
        </UFormField>
        <UFormField label="جملة تحت الاسم" hint="اختياري">
          <UInput :model-value="form.tagline ?? ''" @update:model-value="v => form!.tagline = String(v) || null" class="w-full" placeholder="تالتة ثانوي — نظام جديد" />
        </UFormField>
        <UFormField label="اللون">
          <div class="flex flex-wrap gap-2">
            <button
              v-for="c in site?.colors ?? []"
              :key="c"
              type="button"
              class="size-9 rounded-full ring-offset-2 ring-offset-(--ui-bg) transition"
              :class="[swatch[c], form.color === c ? 'ring-2 ring-(--ui-text)' : '']"
              :aria-label="c"
              :aria-pressed="form.color === c"
              @click="form.color = c"
            />
          </div>
        </UFormField>
      </UCard>

      <UCard :ui="{ body: 'space-y-4' }">
        <template #header>
          <h2 class="font-bold">
            الطلاب
          </h2>
        </template>
        <UFormField label="عدد الأجهزة لكل طالب" description="جهاز جديد فوق العدد ده بيتقفل لحد ما مساعد يفكّ جهاز قديم. ده بيصعّب إن الطالب يدّي حسابه لغيره.">
          <div class="flex gap-1">
            <UButton v-for="n in [1, 2, 3, 4, 5]" :key="n" :color="form.device_limit === n ? 'primary' : 'neutral'" :variant="form.device_limit === n ? 'solid' : 'outline'" :label="String(n)" class="num w-12 justify-center" @click="form.device_limit = n" />
          </div>
        </UFormField>
        <USwitch v-model="form.registration_open" label="التسجيل مفتوح" description="لو قفلته، الطلاب اللي سجّلوا قبل كده بس هيقدروا يدخلوا." />
        <USwitch v-model="form.require_parent_phone" label="رقم ولي الأمر إجباري" description="عشان تقدر تبعتله تقرير الأسبوع." />
      </UCard>

      <UAlert v-if="error" color="error" variant="subtle" :title="error" />
      <UButton type="submit" size="lg" :loading="saving" label="حفظ" />
    </form>
  </div>
</template>

<script setup lang="ts">
import type { Site } from '~/types/manhagy'

definePageMeta({ permission: 'site.manage' })

const api = useApi()
const toast = useToast()
const site = ref<Site | null>(null)
const form = ref<Pick<Site, 'slug' | 'title' | 'tagline' | 'color' | 'device_limit' | 'registration_open' | 'require_parent_phone'> | null>(null)
const saving = ref(false)
const error = ref<string | null>(null)
const errors = ref<Record<string, string>>({})
const copied = ref(false)

const host = computed(() => site.value?.url?.replace(/^https?:\/\/[^.]+\./, '') ?? 'manhagy.online')
const shareLink = computed(() => `https://wa.me/?text=${encodeURIComponent(`ادخلوا على منصتي من هنا وسجّلوا: ${site.value?.url ?? ''}`)}`)

const swatch: Record<string, string> = {
  indigo: 'bg-indigo-600', rose: 'bg-rose-600', teal: 'bg-teal-600', amber: 'bg-amber-500',
  sky: 'bg-sky-600', violet: 'bg-violet-600', emerald: 'bg-emerald-600', slate: 'bg-slate-700',
}

function fill(data: Site) {
  site.value = data
  form.value = { slug: data.slug, title: data.title, tagline: data.tagline, color: data.color, device_limit: data.device_limit, registration_open: data.registration_open, require_parent_phone: data.require_parent_phone }
}

onMounted(async () => fill((await api<{ data: Site }>('/site')).data))

async function save() {
  if (!form.value) return
  saving.value = true
  error.value = null
  errors.value = {}
  try {
    fill((await api<{ data: Site }>('/site', { method: 'PUT', body: form.value })).data)
    refreshNuxtData('my-site')
    toast.add({ color: 'success', title: 'اتحفظت إعدادات المنصة' })
  }
  catch (e) {
    errors.value = apiValidationErrors(e)
    error.value = Object.keys(errors.value).length ? null : apiErrorMessage(e)
  }
  finally {
    saving.value = false
  }
}

async function copy() {
  if (!site.value?.url) return
  await navigator.clipboard.writeText(site.value.url).catch(() => {})
  copied.value = true
  setTimeout(() => (copied.value = false), 2000)
}
</script>
