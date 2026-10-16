<template>
  <div class="max-w-3xl space-y-6">
    <template v-if="!made">
      <PageHeader title="دفعة أكواد جديدة" description="الأكواد هتظهرلك مرة واحدة بس بعد ما تعملها: اطبعها كروت أو نزّلها إكسل وقتها." />
      <form class="space-y-6" @submit.prevent="create">
        <UCard :ui="{ body: 'space-y-4' }">
          <div class="grid grid-cols-2 gap-2">
            <button v-for="k in kinds" :key="k.value" type="button" class="flex items-center gap-3 rounded-(--ui-radius) border p-3 text-start transition" :class="form.kind === k.value ? 'border-primary bg-(--app-primary-soft)' : 'border-(--ui-border)'" @click="form.kind = k.value">
              <UIcon :name="k.icon" class="size-6 text-(--ui-primary)" />
              <span><span class="block font-bold">{{ k.label }}</span><span class="block text-xs text-(--ui-text-muted)">{{ k.hint }}</span></span>
            </button>
          </div>
          <template v-if="form.kind === 'course'">
            <UFormField label="الكورس">
              <USelect v-model="form.course_id" :items="courseItems" class="w-full" placeholder="اختار كورس" />
            </UFormField>
            <UFormField label="مدة الاشتراك بالأيام" hint="اختياري" description="فاضي = مدة الكورس نفسه. «شهر» = 30.">
              <UInputNumber v-model="form.days" :min="1" :max="1000" class="w-full" />
            </UFormField>
          </template>
          <UFormField v-else label="قيمة الكود (ج)" description="بتتحط في محفظة الطالب ويشتري بيها اللي هو عايزه.">
            <UInputNumber v-model="form.value" :min="1" class="w-full" />
          </UFormField>
        </UCard>

        <UCard :ui="{ body: 'space-y-4' }">
          <div class="grid gap-4 sm:grid-cols-2">
            <UFormField label="عدد الأكواد" :description="`لحد ${MAX} في الدفعة`">
              <UInputNumber v-model="form.count" :min="1" :max="MAX" class="w-full" />
            </UFormField>
            <UFormField label="سعر الكارت (ج)" description="اللي الموزّع بيحاسبك عليه. فاضي = سعر الكورس / قيمة الرصيد.">
              <UInputNumber v-model="form.unit_price" :min="0" class="w-full" />
            </UFormField>
            <UFormField label="الموزّع" hint="اختياري">
              <USelect v-model="form.distributor_id" :items="distributorItems" class="w-full" placeholder="من غير موزّع" />
            </UFormField>
            <UFormField label="صالحة لحد" hint="اختياري">
              <UInput v-model="form.expires_at" type="date" class="w-full" />
            </UFormField>
          </div>
          <UFormField label="اسم للدفعة" hint="اختياري">
            <UInput v-model="form.label" class="w-full" placeholder="مكتبة النور — شهر أكتوبر" />
          </UFormField>
        </UCard>

        <UAlert v-if="error" color="error" variant="subtle" :title="error" />
        <UButton type="submit" size="lg" :loading="saving" :disabled="form.kind === 'course' ? !form.course_id : !form.value" :label="`اعمل ${form.count} كود`" />
      </form>
    </template>

    <template v-else>
      <PageHeader :title="`الدفعة ${made.number} جاهزة`" :description="`${made.count} كود ${made.kind === 'course' ? `لـ «${made.course_title}»` : `رصيد ${formatMoney(made.value)}`}`" />
      <UAlert color="warning" variant="subtle" icon="i-lucide-triangle-alert" title="الأكواد دي مش هتظهر تاني" description="اطبعها أو نزّلها دلوقتي. لو قفلت الصفحة من غير ما تحفظها، الغي الدفعة واعمل واحدة جديدة." />
      <div class="flex flex-wrap gap-2">
        <UButton icon="i-lucide-printer" label="اطبع كروت (A4)" @click="print()" />
        <UButton color="neutral" variant="outline" icon="i-lucide-file-spreadsheet" label="نزّل CSV" @click="downloadCsv" />
        <UButton color="neutral" variant="ghost" label="تفاصيل الدفعة" :to="`/codes/${made.id}`" />
      </div>
      <UCard :ui="{ body: 'p-0 sm:p-0' }">
        <div class="grid max-h-96 grid-cols-2 overflow-y-auto sm:grid-cols-3">
          <div v-for="c in made.codes" :key="c.serial" class="flex items-center gap-2 border-b border-e border-(--ui-border) px-3 py-2">
            <span class="num text-xs text-(--ui-text-muted)">#{{ c.serial }}</span>
            <span class="num font-bold" dir="ltr">{{ c.code }}</span>
          </div>
        </div>
      </UCard>
      <CodeCards v-if="printing" :codes="made.codes ?? []" :title="store.session?.tenant.name ?? ''" :what="made.kind === 'course' ? `كورس «${made.course_title}»` : `رصيد ${formatMoney(made.value)} في محفظتك`" :site-url="site.url.value" :site-host="site.host.value" :expires="made.expires_at ? formatDate(made.expires_at) : null" />
    </template>
  </div>
</template>

<script setup lang="ts">
import type { CodeBatch, CourseSummary, Distributor } from '~/types/manhagy'

definePageMeta({ permission: 'codes.manage' })

const MAX = 2000
const api = useApi()
const store = useSessionStore()
const route = useRoute()
const site = useSiteAddress()
const { printing, print } = usePrint()

const kinds = [
  { value: 'course', label: 'كود كورس', hint: 'يفتح كورس معيّن', icon: 'i-lucide-book-open-text' },
  { value: 'balance', label: 'كود رصيد', hint: 'فلوس في المحفظة', icon: 'i-lucide-wallet' },
] as const
const form = reactive({
  kind: 'course' as 'course' | 'balance',
  course_id: typeof route.query.course === 'string' ? route.query.course : '',
  days: null as number | null,
  value: 100,
  count: 50,
  unit_price: null as number | null,
  distributor_id: '',
  expires_at: '',
  label: '',
})
const saving = ref(false)
const error = ref<string | null>(null)
const made = ref<CodeBatch | null>(null)

const { data: courses } = await useAsyncData('courses-for-codes', async () => (await api<{ data: CourseSummary[] }>('/courses')).data)
const courseItems = computed(() => (courses.value ?? []).filter(c => c.status !== 'archived').map(c => ({ value: c.id, label: `${c.title} · ${formatMoney(c.price)}` })))
const { data: distributors } = await useAsyncData('distributors-for-codes', async () => (await api<{ data: Distributor[] }>('/codes/distributors')).data)
const distributorItems = computed(() => (distributors.value ?? []).filter(d => d.active).map(d => ({ value: d.id, label: d.name })))

async function create() {
  saving.value = true
  error.value = null
  try {
    made.value = (await api<{ data: CodeBatch }>('/codes/batches', { method: 'POST', body: {
      kind: form.kind,
      course_id: form.kind === 'course' ? form.course_id : null,
      days: form.kind === 'course' ? form.days || null : null,
      value: form.kind === 'balance' ? Math.round(form.value * 100) : null,
      unit_price: form.unit_price === null ? null : Math.round(form.unit_price * 100),
      count: form.count,
      distributor_id: form.distributor_id || null,
      expires_at: form.expires_at ? new Date(`${form.expires_at}T23:59:59`).toISOString() : null,
      label: form.label || null,
    } })).data
  }
  catch (e) {
    error.value = apiErrorMessage(e)
  }
  finally {
    saving.value = false
  }
}

onBeforeRouteLeave(() => !made.value || confirm('الأكواد مش هتظهر تاني. طبعتها أو نزّلتها؟'))

function downloadCsv() {
  if (!made.value?.codes) return
  const rows = [['#', 'الكود'], ...made.value.codes.map(c => [String(c.serial), c.code])]
  const blob = new Blob(['﻿' + rows.map(r => r.join(',')).join('\n')], { type: 'text/csv;charset=utf-8' })
  const a = document.createElement('a')
  a.href = URL.createObjectURL(blob)
  a.download = `${made.value.number}.csv`
  a.click()
  URL.revokeObjectURL(a.href)
}
</script>
