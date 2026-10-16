<template>
  <div class="max-w-5xl space-y-6">
    <PageHeader title="المدرسين ونصيبهم" description="نصيب كل مدرس من مجموعاته: نسبة من اللي اتحصّل أو مبلغ ثابت على كل حضور.">
      <UInput v-model="month" type="month" class="w-40" />
      <UButton icon="i-lucide-plus" label="مدرس" @click="edit(null)" />
    </PageHeader>

    <p v-if="!teachers?.length" class="app-card p-8 text-center text-sm text-(--ui-text-muted)">
      ضيف المدرسين اللي بيدرّسوا عندك، واربط كل مجموعة بمدرسها.
    </p>
    <div class="grid gap-3 md:grid-cols-2">
      <div v-for="t in teachers" :key="t.id" class="app-card space-y-3 p-4" :class="{ 'opacity-60': !t.active }">
        <div class="flex items-start justify-between gap-2">
          <div>
            <p class="font-bold">
              {{ t.name }}
            </p>
            <p class="text-xs text-(--ui-text-muted)">
              {{ t.subject }} · {{ t.share_kind === 'percent' ? `${t.share_value}% من التحصيل` : `${formatMoney(t.share_value)} على كل حضور` }}
            </p>
          </div>
          <UButton size="xs" color="neutral" variant="ghost" icon="i-lucide-pencil" aria-label="تعديل" @click="edit(t)" />
        </div>
        <div v-if="t.statement" class="grid grid-cols-3 gap-2 text-center text-sm">
          <div><p class="num font-bold">{{ t.statement.attendances }}</p><p class="text-xs text-(--ui-text-muted)">حضور</p></div>
          <div><p class="num font-bold">{{ formatMoney(t.statement.collected) }}</p><p class="text-xs text-(--ui-text-muted)">اتحصّل</p></div>
          <div><p class="num font-bold" :class="t.statement.remaining > 0 ? 'text-(--ui-warning)' : ''">{{ formatMoney(t.statement.remaining) }}</p><p class="text-xs text-(--ui-text-muted)">ليه (فاضل)</p></div>
        </div>
        <UButton v-if="t.statement && t.statement.remaining > 0" block size="sm" icon="i-lucide-hand-coins" :label="`سلّمه ${formatMoney(t.statement.remaining)}`" @click="settle(t)" />
      </div>
    </div>

    <UModal v-model:open="formOpen" :title="form.id ? 'تعديل المدرس' : 'مدرس جديد'">
      <template #body>
        <form class="space-y-4" @submit.prevent="save">
          <UFormField label="الاسم">
            <UInput v-model="form.name" class="w-full" />
          </UFormField>
          <div class="grid grid-cols-2 gap-3">
            <UFormField label="المادة" hint="اختياري">
              <UInput v-model="form.subject" class="w-full" />
            </UFormField>
            <UFormField label="الموبايل" hint="اختياري">
              <UInput v-model="form.phone" dir="ltr" class="w-full" />
            </UFormField>
          </div>
          <UFormField label="النصيب">
            <div class="flex gap-2">
              <USelect v-model="form.share_kind" :items="[{ value: 'percent', label: 'نسبة من التحصيل %' }, { value: 'per_student', label: 'مبلغ على كل حضور (ج)' }]" class="flex-1" />
              <UInputNumber v-model="form.value" :min="0" class="w-32" />
            </div>
          </UFormField>
          <USwitch v-if="form.id" v-model="form.active" label="شغال" />
          <div class="flex justify-end gap-2">
            <UButton color="neutral" variant="ghost" label="إلغاء" @click="formOpen = false" />
            <UButton type="submit" :disabled="!form.name.trim()" label="حفظ" />
          </div>
        </form>
      </template>
    </UModal>
  </div>
</template>

<script setup lang="ts">
import type { CenterTeacher } from '~/types/manhagy'

definePageMeta({ module: 'center', permission: 'center.cash' })

const api = useApi()
const toast = useToast()
const now = new Date()
const month = ref(`${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`)
const range = computed(() => {
  const [y, m] = month.value.split('-').map(Number) as [number, number]
  const last = new Date(y, m, 0).getDate()
  return { from: `${month.value}-01`, to: `${month.value}-${String(last).padStart(2, '0')}` }
})
const { data: teachers, refresh } = await useAsyncData('center-teachers', async () => (await api<{ data: CenterTeacher[] }>('/center/teachers', { query: range.value })).data, { watch: [range] })

const formOpen = ref(false)
const form = reactive({ id: '', name: '', subject: '', phone: '', share_kind: 'percent' as CenterTeacher['share_kind'], value: 0, active: true })
function edit(t: CenterTeacher | null) {
  Object.assign(form, t
    ? { id: t.id, name: t.name, subject: t.subject ?? '', phone: t.phone ?? '', share_kind: t.share_kind, value: t.share_kind === 'percent' ? t.share_value : t.share_value / 100, active: t.active }
    : { id: '', name: '', subject: '', phone: '', share_kind: 'percent', value: 50, active: true })
  formOpen.value = true
}
async function save() {
  const body = { name: form.name, subject: form.subject || null, phone: form.phone || null, share_kind: form.share_kind, share_value: form.share_kind === 'percent' ? Math.round(form.value) : Math.round(form.value * 100), ...(form.id ? { active: form.active } : {}) }
  try {
    await api(form.id ? `/center/teachers/${form.id}` : '/center/teachers', { method: form.id ? 'PATCH' : 'POST', body })
    formOpen.value = false
    await refresh()
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
}
async function settle(t: CenterTeacher) {
  if (!t.statement || !confirm(`تسجّل إنك سلّمت ${t.name} ${formatMoney(t.statement.remaining)}؟`)) return
  try {
    await api(`/center/teachers/${t.id}/settlements`, { method: 'POST', body: { ...range.value, paid: t.statement.remaining } })
    await refresh()
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
}
</script>
