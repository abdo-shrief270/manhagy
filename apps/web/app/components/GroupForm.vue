<template>
  <UModal v-model:open="open" :title="group ? 'تعديل المجموعة' : 'مجموعة جديدة'" :ui="{ content: 'sm:max-w-xl' }">
    <template #body>
      <form class="space-y-4" @submit.prevent="save">
        <UFormField label="اسم المجموعة">
          <UInput v-model="form.name" class="w-full" placeholder="فيزياء — مجموعة السبت" />
        </UFormField>
        <div class="grid gap-3 sm:grid-cols-2">
          <UFormField label="المدرس">
            <USelect v-model="form.teacher_id" :items="[{ value: '', label: 'أنا (الحساب)' }, ...teachers.map(t => ({ value: t.id, label: t.name }))]" class="w-full" />
          </UFormField>
          <UFormField label="القاعة" hint="اختياري">
            <UInput v-model="form.room" class="w-full" placeholder="قاعة 2" />
          </UFormField>
          <UFormField label="سعر الحصة (ج)">
            <UInputNumber v-model="form.session" :min="0" class="w-full" />
          </UFormField>
          <UFormField label="اشتراك الشهر (ج)" hint="اختياري">
            <UInputNumber v-model="form.monthly" :min="0" class="w-full" />
          </UFormField>
          <UFormField label="أقصى عدد" hint="اختياري">
            <UInputNumber v-model="form.capacity" :min="1" class="w-full" />
          </UFormField>
        </div>
        <div class="space-y-2">
          <p class="text-sm font-bold">
            المواعيد كل أسبوع
          </p>
          <div v-for="(slot, i) in form.schedule" :key="i" class="flex flex-wrap items-center gap-2">
            <USelect v-model="slot.day" :items="DAY_NAMES.map((label, value) => ({ value, label }))" class="w-32" />
            <UInput v-model="slot.time" type="time" class="w-32" />
            <UInputNumber v-model="slot.minutes" :min="15" :max="300" :step="15" class="w-32" />
            <span class="text-xs text-(--ui-text-muted)">دقيقة</span>
            <UButton size="xs" color="neutral" variant="ghost" icon="i-lucide-x" aria-label="شيل" @click="form.schedule.splice(i, 1)" />
          </div>
          <UButton size="xs" variant="ghost" icon="i-lucide-plus" label="ميعاد" @click="form.schedule.push({ day: 6, time: '16:00', minutes: 90 })" />
        </div>
        <USwitch v-if="group" v-model="form.active" label="المجموعة شغالة" />
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
import type { CenterGroup, CenterTeacher, ScheduleSlot } from '~/types/manhagy'

const props = defineProps<{ group: CenterGroup | null }>()
const emit = defineEmits<{ saved: [group: CenterGroup] }>()
const open = defineModel<boolean>('open', { default: false })

const api = useApi()
const store = useSessionStore()
const teachers = ref<CenterTeacher[]>([])
const form = reactive({ name: '', teacher_id: '', room: '', session: 0, monthly: null as number | null, capacity: null as number | null, schedule: [] as ScheduleSlot[], active: true })
const saving = ref(false)
const error = ref<string | null>(null)

watch(open, async (isOpen) => {
  if (!isOpen) return
  error.value = null
  const g = props.group
  Object.assign(form, {
    name: g?.name ?? '', teacher_id: g?.teacher_id ?? '', room: g?.room ?? '',
    session: (g?.session_fee ?? 0) / 100, monthly: g?.monthly_fee ? g.monthly_fee / 100 : null, capacity: g?.capacity ?? null,
    schedule: g ? g.schedule.map(s => ({ ...s })) : [{ day: 6, time: '16:00', minutes: 90 }], active: g?.active ?? true,
  })
  if (store.can('center.cash')) {
    teachers.value = (await api<{ data: CenterTeacher[] }>('/center/teachers').catch(() => ({ data: [] }))).data.filter(t => t.active)
  }
})

async function save() {
  saving.value = true
  error.value = null
  const body = {
    name: form.name, teacher_id: form.teacher_id || null, room: form.room || null,
    session_fee: Math.round(form.session * 100), monthly_fee: form.monthly ? Math.round(form.monthly * 100) : null,
    capacity: form.capacity || null, schedule: form.schedule, ...(props.group ? { active: form.active } : {}),
  }
  try {
    const res = await api<{ data: CenterGroup }>(props.group ? `/center/groups/${props.group.id}` : '/center/groups', { method: props.group ? 'PATCH' : 'POST', body })
    open.value = false
    emit('saved', res.data)
  }
  catch (e) {
    error.value = apiErrorMessage(e)
  }
  finally {
    saving.value = false
  }
}
</script>
