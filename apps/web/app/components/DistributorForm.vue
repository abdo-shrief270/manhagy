<template>
  <UModal v-model:open="open" :title="distributor ? 'تعديل الموزّع' : 'موزّع جديد'">
    <template #body>
      <form class="space-y-4" @submit.prevent="save">
        <UFormField label="الاسم">
          <UInput v-model="form.name" class="w-full" placeholder="مكتبة النور" />
        </UFormField>
        <UFormField label="الموبايل" hint="اختياري">
          <UInput v-model="form.phone" dir="ltr" inputmode="tel" class="w-full" placeholder="01xxxxxxxxx" />
        </UFormField>
        <UFormField label="النوع">
          <USelect v-model="form.kind" :items="kinds" class="w-full" />
        </UFormField>
        <UFormField label="ملاحظات" hint="اختياري">
          <UTextarea v-model="form.notes" :rows="2" class="w-full" placeholder="بياخد 10% من كل كارت" />
        </UFormField>
        <USwitch v-if="distributor" v-model="form.active" label="شغّال" />
        <UAlert v-if="error" color="error" variant="subtle" :title="error" />
        <div class="flex justify-end gap-2">
          <UButton color="neutral" variant="ghost" label="إلغاء" @click="open = false" />
          <UButton type="submit" :loading="saving" :disabled="!form.name.trim()" label="حفظ" />
        </div>
      </form>
    </template>
  </UModal>
</template>

<script setup lang="ts">
import type { Distributor } from '~/types/manhagy'

const props = defineProps<{ distributor: Distributor | null }>()
const emit = defineEmits<{ saved: [] }>()
const open = defineModel<boolean>('open', { default: false })

const api = useApi()
const kinds = [{ value: 'library', label: 'مكتبة' }, { value: 'center', label: 'سنتر' }, { value: 'assistant', label: 'مساعد' }, { value: 'other', label: 'غير كده' }]
const form = reactive({ name: '', phone: '', kind: 'library', notes: '', active: true })
const saving = ref(false)
const error = ref<string | null>(null)

watch(open, (isOpen) => {
  if (isOpen) {
    const d = props.distributor
    Object.assign(form, { name: d?.name ?? '', phone: d?.phone ?? '', kind: d?.kind ?? 'library', notes: d?.notes ?? '', active: d?.active ?? true })
    error.value = null
  }
})

async function save() {
  saving.value = true
  error.value = null
  try {
    const body = { name: form.name, phone: form.phone || null, kind: form.kind, notes: form.notes || null, ...(props.distributor ? { active: form.active } : {}) }
    await api(props.distributor ? `/codes/distributors/${props.distributor.id}` : '/codes/distributors', { method: props.distributor ? 'PATCH' : 'POST', body })
    open.value = false
    emit('saved')
  }
  catch (e) {
    error.value = apiErrorMessage(e)
  }
  finally {
    saving.value = false
  }
}
</script>
