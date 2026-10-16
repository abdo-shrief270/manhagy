<template>
  <UCard :ui="{ body: 'space-y-4' }">
    <template #header>
      <div class="flex items-center gap-2">
        <UIcon name="i-lucide-award" class="size-5 text-(--ui-primary)" />
        <h2 class="flex-1 font-bold">
          الشهادة
        </h2>
        <NuxtLink v-if="form && form.issued" :to="`/certificates?course=${courseId}`" class="num text-sm text-primary hover:underline">{{ form.issued }} شهادة</NuxtLink>
      </div>
    </template>
    <USkeleton v-if="!form" class="h-24" />
    <template v-else>
      <USwitch v-model="form.enabled" label="الطالب ياخد شهادة لما يخلّص" description="بكود تحقق ولينك يقدر يبعته لأي حد أو يحطه في الـ CV." />
      <template v-if="form.enabled">
        <UFormField label="عنوان الشهادة">
          <UInput v-model="form.title" class="w-full" placeholder="شهادة إتمام" />
        </UFormField>
        <div class="flex flex-wrap items-center gap-3">
          <USwitch :model-value="form.min_exam_percent !== null" label="لازم ينجح في الامتحانات" @update:model-value="v => form!.min_exam_percent = v ? 50 : null" />
          <UInputNumber v-if="form.min_exam_percent !== null" v-model="form.min_exam_percent" :min="1" :max="100" class="w-28" :format-options="{ style: 'unit', unit: 'percent' }" />
        </div>
        <UFormField label="اسم اللي بيمضي" hint="اختياري">
          <UInput :model-value="form.signer_name ?? ''" class="w-full" placeholder="أ. محمد سامي" @update:model-value="v => form!.signer_name = String(v) || null" />
        </UFormField>
        <UFormField label="صفته" hint="اختياري">
          <UInput :model-value="form.signer_title ?? ''" class="w-full" placeholder="مدرس الفيزياء" @update:model-value="v => form!.signer_title = String(v) || null" />
        </UFormField>
      </template>
      <UButton v-if="canManage" block variant="soft" :loading="saving" label="حفظ الشهادة" @click="save" />
    </template>
  </UCard>
</template>

<script setup lang="ts">
import type { CertificateSettings } from '~/types/manhagy'

const props = defineProps<{ courseId: string }>()
const api = useApi()
const toast = useToast()
const store = useSessionStore()
const canManage = computed(() => store.can('certificates.manage'))
const form = ref<CertificateSettings | null>(null)
const saving = ref(false)

onMounted(async () => {
  form.value = (await api<{ data: CertificateSettings }>(`/certificates/courses/${props.courseId}`)).data
})

async function save() {
  if (!form.value) return
  saving.value = true
  try {
    const { enabled, min_exam_percent, title, signer_name, signer_title } = form.value
    form.value = (await api<{ data: CertificateSettings }>(`/certificates/courses/${props.courseId}`, { method: 'PUT', body: { enabled, min_exam_percent, title: title || 'شهادة إتمام', signer_name, signer_title } })).data
    toast.add({ color: 'success', title: 'اتحفظت إعدادات الشهادة' })
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    saving.value = false
  }
}
</script>
