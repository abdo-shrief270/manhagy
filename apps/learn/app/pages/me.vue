<template>
  <div class="mx-auto max-w-2xl space-y-4">
    <UCard v-if="me" :ui="{ body: 'space-y-4' }">
      <template #header>
        <h1 class="text-xl font-bold">
          حسابي
        </h1>
      </template>
      <UFormField label="الاسم">
        <UInput v-model="name" class="w-full" />
      </UFormField>
      <UFormField label="رقم ولي الأمر" hint="اختياري">
        <UInput v-model="parent" dir="ltr" inputmode="tel" class="w-full" placeholder="01xxxxxxxxx" />
      </UFormField>
      <p class="text-sm text-(--ui-text-muted)">
        موبايلك: <span class="num">{{ localPhone(me.phone) }}</span>
        <UBadge v-if="me.phone_verified" size="sm" color="success" variant="subtle" label="متأكد" />
        <span v-if="me.grade"> · {{ me.grade }}</span>
      </p>
      <UAlert v-if="error" color="error" variant="subtle" :title="error" />
      <UButton :loading="saving" label="حفظ" @click="save" />
    </UCard>

    <UCard v-if="me" :ui="{ body: 'p-0 sm:p-0' }">
      <template #header>
        <h2 class="font-bold">
          أجهزتك
        </h2>
        <p class="text-sm text-(--ui-text-muted)">
          عشان تدخل من جهاز جديد بعد ما توصل للحد، كلّم المدرس يفكّ جهاز.
        </p>
      </template>
      <div v-for="d in me.devices" :key="d.id" class="flex items-center gap-3 border-b border-(--ui-border) px-4 py-3 text-sm last:border-0">
        <UIcon name="i-lucide-smartphone" class="size-4 text-(--ui-text-muted)" />
        <span class="flex-1">{{ d.name }}</span>
        <UBadge v-if="d.current" size="sm" color="primary" variant="subtle" label="الجهاز ده" />
      </div>
    </UCard>

    <UCard v-if="certificates.length" :ui="{ body: 'p-0 sm:p-0' }">
      <template #header>
        <h2 class="font-bold">
          شهاداتي
        </h2>
      </template>
      <NuxtLink v-for="c in certificates" :key="c.code" :to="`/certificates/${c.code}`" class="flex items-center gap-3 border-b border-(--ui-border) px-4 py-3 text-sm last:border-0 hover:bg-(--ui-bg-elevated)/60">
        <UIcon name="i-lucide-award" class="size-5 text-primary" />
        <span class="flex-1 font-bold">{{ c.course_title }}</span>
        <span class="text-xs text-(--ui-text-muted)">{{ formatDate(c.issued_at) }}</span>
      </NuxtLink>
    </UCard>

    <UButton block color="error" variant="soft" icon="i-lucide-log-out" label="خروج" @click="learner.logout()" />
  </div>
</template>

<script setup lang="ts">
import type { Me } from '~/utils/types'

definePageMeta({ auth: true })

const learner = useLearner()
const api = useLearnApi()
const toast = useToast()
const me = computed(() => learner.me.value)
const name = ref(me.value?.name ?? '')
const parent = ref(localPhone(me.value?.parent_phone))
const saving = ref(false)
const error = ref<string | null>(null)
const { data: certificateList } = await useAsyncData('my-certificates', async () => (await api<{ data: { code: string, course_title: string, issued_at: string }[] }>('/learner/certificates')).data)
const certificates = computed(() => certificateList.value ?? [])

async function save() {
  saving.value = true
  error.value = null
  try {
    learner.me.value = (await api<{ data: Me }>('/learner/me', { method: 'PUT', body: { name: name.value, parent_phone: parent.value || null } })).data
    toast.add({ color: 'success', title: 'اتحفظ' })
  }
  catch (e) {
    error.value = apiMessage(e)
  }
  finally {
    saving.value = false
  }
}
</script>
