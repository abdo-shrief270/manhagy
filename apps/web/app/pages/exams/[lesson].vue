<template>
  <div class="max-w-5xl space-y-6">
    <USkeleton v-if="!exam" class="h-64" />
    <template v-else>
      <PageHeader :title="exam.title" description="الوقت بيتحسب على السيرفر، وترتيب الأسئلة بيختلف من طالب للتاني، وكل إجابة بتتحفظ لوحدها.">
        <UButton color="neutral" variant="outline" icon="i-lucide-arrow-right" label="الكورس" :to="`/courses/${exam.course_id}`" />
      </PageHeader>

      <UTabs v-model="tab" :items="tabs" :content="false" />

      <form v-if="tab === 'setup'" class="space-y-6" @submit.prevent="save">
        <UAlert v-if="locked" color="warning" variant="subtle" icon="i-lucide-lock" :title="`${exam.exam?.attempts} طالب بدأوا الامتحان`" description="تقدر تغيّر المواعيد والإعدادات، بس الأسئلة ودرجاتها اتثبتت." />
        <UCard :ui="{ body: 'grid gap-4 sm:grid-cols-2' }">
          <UFormField label="المدة (دقيقة)">
            <UInputNumber v-model="form.duration_minutes" :min="1" :max="600" class="w-full" />
          </UFormField>
          <UFormField label="عدد المحاولات">
            <UInputNumber v-model="form.attempts_allowed" :min="1" :max="10" class="w-full" />
          </UFormField>
          <UFormField label="يفتح" hint="اختياري">
            <UInput v-model="form.opens_at" type="datetime-local" class="w-full" />
          </UFormField>
          <UFormField label="يقفل" hint="اختياري" description="بعدها محدش يبدأ، واللي بيمتحن يتسلّم امتحانه.">
            <UInput v-model="form.closes_at" type="datetime-local" class="w-full" />
          </UFormField>
          <UFormField label="درجة النجاح %">
            <UInputNumber v-model="form.pass_percent" :min="0" :max="100" class="w-full" />
          </UFormField>
          <UFormField label="الإجابات الصح تظهر">
            <USelect v-model="form.show_answers" :items="showAnswers" class="w-full" />
          </UFormField>
          <USwitch v-model="form.shuffle_questions" label="رتّب الأسئلة مختلف لكل طالب" />
          <USwitch v-model="form.shuffle_options" label="رتّب الاختيارات مختلف لكل طالب" />
        </UCard>

        <UCard :ui="{ body: 'p-0 sm:p-0' }">
          <template #header>
            <div class="flex flex-wrap items-center justify-between gap-2">
              <h2 class="font-bold">
                الأسئلة: <span class="num">{{ picked.length }}</span> · <span class="num">{{ total }}</span> درجة
              </h2>
              <UButton size="sm" color="neutral" variant="outline" icon="i-lucide-list-checks" label="بنك الأسئلة" :to="`/assessments/questions?course=${exam.course_id}`" />
            </div>
          </template>
          <p v-if="!bank?.length" class="p-6 text-center text-sm text-(--ui-text-muted)">
            بنك أسئلة الكورس فاضي. ضيف أسئلة الأول.
          </p>
          <div v-else class="divide-y divide-(--ui-border)">
            <label v-for="q in bank" :key="q.id" class="flex cursor-pointer items-start gap-3 px-4 py-3" :class="{ 'opacity-60': locked && !isPicked(q.id) }">
              <UCheckbox :model-value="isPicked(q.id)" :disabled="locked" class="mt-1" @update:model-value="toggle(q)" />
              <span class="min-w-0 flex-1">
                <span class="block whitespace-pre-line text-sm font-bold">{{ q.body }}</span>
                <span class="text-xs text-(--ui-text-muted)">{{ QUESTION_KINDS[q.kind].label }} · {{ DIFFICULTY[q.difficulty] }}</span>
              </span>
              <UInputNumber v-if="isPicked(q.id)" :model-value="pointsOf(q.id)" :min="1" :max="100" :disabled="locked" class="w-24" @update:model-value="v => setPoints(q.id, Number(v))" @click.prevent />
            </label>
          </div>
        </UCard>

        <UAlert v-if="error" color="error" variant="subtle" :title="error" />
        <UButton type="submit" size="lg" :loading="saving" :disabled="!picked.length" label="حفظ الامتحان" />
      </form>

      <template v-else-if="results">
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
          <StatTile label="سلّموا" :value="String(results.summary.attempts)" icon="i-lucide-users-round" />
          <StatTile label="بيمتحنوا دلوقتي" :value="String(results.summary.in_progress)" icon="i-lucide-timer" />
          <StatTile label="المتوسط" :value="results.summary.average_percent === null ? '—' : `${results.summary.average_percent}%`" icon="i-lucide-chart-column" />
          <StatTile label="نسبة النجاح" :value="results.summary.pass_percent === null ? '—' : `${results.summary.pass_percent}%`" icon="i-lucide-trophy" />
        </div>
        <div class="grid gap-4 lg:grid-cols-2">
          <UCard :ui="{ body: 'p-0 sm:p-0' }">
            <template #header>
              <h2 class="font-bold">
                الطلاب
              </h2>
            </template>
            <p v-if="!results.attempts.length" class="p-6 text-center text-sm text-(--ui-text-muted)">
              لسه محدش امتحن.
            </p>
            <div v-for="a in results.attempts" :key="a.id" class="flex items-center gap-3 border-b border-(--ui-border) px-4 py-2.5 text-sm last:border-0">
              <span class="flex-1 font-bold">{{ a.learner_name }}<span v-if="a.number > 1" class="text-xs font-normal text-(--ui-text-muted)"> · محاولة {{ a.number }}</span></span>
              <UTooltip v-if="a.leaves" :text="`خرج من صفحة الامتحان ${a.leaves} مرة`">
                <UBadge size="sm" color="warning" variant="subtle" icon="i-lucide-log-out" class="num" :label="String(a.leaves)" />
              </UTooltip>
              <span v-if="a.status === 'in_progress'" class="text-(--ui-text-muted)">بيمتحن…</span>
              <template v-else>
                <span class="num">{{ a.score }} / {{ a.max_score }}</span>
                <UBadge :color="a.passed ? 'success' : 'error'" variant="subtle" class="num" :label="`${a.percent}%`" />
              </template>
            </div>
          </UCard>
          <UCard :ui="{ body: 'p-0 sm:p-0' }">
            <template #header>
              <h2 class="font-bold">
                الأسئلة (الأصعب الأول)
              </h2>
            </template>
            <div v-for="q in results.questions" :key="q.id" class="flex items-center gap-3 border-b border-(--ui-border) px-4 py-2.5 text-sm last:border-0">
              <span class="flex-1 truncate">{{ q.body }}</span>
              <span class="num font-bold" :class="(q.correct_percent ?? 100) < 50 ? 'text-(--ui-error)' : ''">{{ q.correct_percent === null ? '—' : `${q.correct_percent}%` }}</span>
            </div>
          </UCard>
        </div>
      </template>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { ExamDetail, ExamResults, Question } from '~/types/manhagy'

definePageMeta({ permission: 'assessments.manage' })

const api = useApi()
const toast = useToast()
const store = useSessionStore()
const lesson = String(useRoute().params.lesson)

const { data: exam, refresh } = await useAsyncData(`exam-${lesson}`, async () => (await api<{ data: ExamDetail }>(`/assessments/exams/${lesson}`)).data)
const { data: bank } = await useAsyncData(`exam-bank-${lesson}`, async () => exam.value ? (await api<{ data: Question[] }>('/assessments/questions', { query: { course_id: exam.value.course_id } })).data.filter(q => q.active || exam.value!.questions.some(p => p.id === q.id)) : [])

const tabs = computed(() => [
  { label: 'الإعداد', value: 'setup', icon: 'i-lucide-settings' },
  ...(store.can('assessments.view') && exam.value?.exam ? [{ label: 'النتايج', value: 'results', icon: 'i-lucide-chart-column' }] : []),
])
const tab = ref('setup')
const { data: results } = await useAsyncData(`exam-results-${lesson}`, async () => tab.value === 'results' ? (await api<{ data: ExamResults }>(`/assessments/exams/${lesson}/results`)).data : null, { watch: [tab] })

const showAnswers = [
  { value: 'after_submit', label: 'بعد ما يسلّم على طول' },
  { value: 'after_close', label: 'بعد ما الامتحان يقفل' },
  { value: 'never', label: 'ما تظهرش' },
]

function toLocalInput(iso: string | null | undefined): string {
  if (!iso) return ''
  const d = new Date(iso)
  return new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 16)
}
const settings = exam.value?.exam
const form = reactive({
  duration_minutes: settings?.duration_minutes ?? 30,
  attempts_allowed: settings?.attempts_allowed ?? 1,
  opens_at: toLocalInput(settings?.opens_at),
  closes_at: toLocalInput(settings?.closes_at),
  pass_percent: settings?.pass_percent ?? 50,
  show_answers: settings?.show_answers ?? 'after_close',
  shuffle_questions: settings?.shuffle_questions ?? true,
  shuffle_options: settings?.shuffle_options ?? true,
})
const picked = ref<{ id: string, points: number }[]>(exam.value?.questions.map(q => ({ id: q.id, points: q.points })) ?? [])
const locked = computed(() => (exam.value?.exam?.attempts ?? 0) > 0)
const total = computed(() => picked.value.reduce((s, p) => s + p.points, 0))

const isPicked = (id: string) => picked.value.some(p => p.id === id)
const pointsOf = (id: string) => picked.value.find(p => p.id === id)?.points ?? 1
function toggle(q: Question) {
  picked.value = isPicked(q.id) ? picked.value.filter(p => p.id !== q.id) : [...picked.value, { id: q.id, points: q.points }]
}
function setPoints(id: string, points: number) {
  const row = picked.value.find(p => p.id === id)
  if (row && points > 0) row.points = points
}

const saving = ref(false)
const error = ref<string | null>(null)
async function save() {
  saving.value = true
  error.value = null
  try {
    await api(`/assessments/exams/${lesson}`, { method: 'PUT', body: {
      ...form,
      opens_at: form.opens_at ? new Date(form.opens_at).toISOString() : null,
      closes_at: form.closes_at ? new Date(form.closes_at).toISOString() : null,
      questions: picked.value,
    } })
    toast.add({ color: 'success', title: 'الامتحان اتحفظ' })
    await refresh()
  }
  catch (e) {
    error.value = apiErrorMessage(e)
  }
  finally {
    saving.value = false
  }
}
</script>
