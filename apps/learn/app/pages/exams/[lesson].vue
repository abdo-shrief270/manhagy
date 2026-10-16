<template>
  <div class="mx-auto max-w-2xl space-y-4">
    <USkeleton v-if="!overview" class="h-48" />

    <!-- Before / between attempts -->
    <template v-else-if="!paper && !result">
      <UCard :ui="{ body: 'space-y-3' }">
        <h1 class="text-xl font-extrabold">
          {{ overview.title }}
        </h1>
        <div v-if="overview.ready" class="grid grid-cols-3 gap-2 text-center">
          <div class="rounded-(--ui-radius) bg-(--ui-bg-elevated) p-2">
            <p class="num text-xl font-bold">
              {{ overview.questions_count }}
            </p>
            <p class="text-xs text-(--ui-text-muted)">
              سؤال
            </p>
          </div>
          <div class="rounded-(--ui-radius) bg-(--ui-bg-elevated) p-2">
            <p class="num text-xl font-bold">
              {{ overview.duration_minutes }}
            </p>
            <p class="text-xs text-(--ui-text-muted)">
              دقيقة
            </p>
          </div>
          <div class="rounded-(--ui-radius) bg-(--ui-bg-elevated) p-2">
            <p class="num text-xl font-bold">
              {{ overview.attempts_used }} / {{ overview.attempts_allowed }}
            </p>
            <p class="text-xs text-(--ui-text-muted)">
              محاولات
            </p>
          </div>
        </div>
        <p v-else class="text-(--ui-text-muted)">
          الامتحان لسه بيتجهّز.
        </p>
        <p v-if="overview.opens_at && new Date(overview.opens_at) > new Date()" class="text-sm">
          يفتح {{ formatDate(overview.opens_at, true) }}
        </p>
        <p v-if="overview.closes_at" class="text-sm text-(--ui-text-muted)">
          يقفل {{ formatDate(overview.closes_at, true) }}
        </p>
        <ul class="list-disc space-y-1 ps-5 text-sm text-(--ui-text-muted)">
          <li>الوقت بيبدأ من أول ما تدوس «ابدأ»، وبيتحسب على السيرفر.</li>
          <li>كل إجابة بتتحفظ أول ما تختارها، لو النت قطع ارجع كمّل.</li>
          <li>لو الوقت خلص، الامتحان بيتسلّم بإجاباتك اللي اتحفظت.</li>
          <li>الخروج من صفحة الامتحان بيتسجّل للمدرس.</li>
        </ul>
        <UAlert v-if="error" color="error" variant="subtle" :title="error" />
        <UButton v-if="overview.in_progress" block size="lg" icon="i-lucide-play" label="كمّل الامتحان" :loading="starting" @click="start" />
        <UButton v-else-if="overview.ready && !overview.locked && overview.attempts_used < (overview.attempts_allowed ?? 1)" block size="lg" icon="i-lucide-play" label="ابدأ الامتحان" :loading="starting" @click="start" />
      </UCard>
      <UCard v-if="overview.attempts.length" :ui="{ body: 'p-0 sm:p-0' }">
        <template #header>
          <h2 class="font-bold">
            محاولاتك
          </h2>
        </template>
        <button v-for="a in overview.attempts" :key="a.id" type="button" class="flex w-full items-center gap-3 border-b border-(--ui-border) px-4 py-3 text-start text-sm last:border-0 hover:bg-(--ui-bg-elevated)/50" @click="showResult(a.id)">
          <span class="flex-1">المحاولة <span class="num">{{ a.number }}</span></span>
          <span class="num font-bold">{{ a.score }} / {{ a.max_score }}</span>
          <UBadge :color="a.passed ? 'success' : 'error'" variant="subtle" class="num" :label="`${a.percent}%`" />
        </button>
      </UCard>
    </template>

    <!-- Sitting -->
    <template v-else-if="paper">
      <div class="sticky top-16 z-10 flex items-center justify-between rounded-(--ui-radius) border border-(--ui-border) bg-(--ui-bg) px-4 py-2 shadow-sm">
        <span class="text-sm"><span class="num font-bold">{{ answeredCount }}</span> من <span class="num">{{ paper.questions.length }}</span> اتجاوب</span>
        <span class="num text-lg font-extrabold" :class="remaining < 60 ? 'text-(--ui-error)' : ''" dir="ltr">{{ clock }}</span>
      </div>
      <UCard v-for="(q, i) in paper.questions" :key="q.id" :ui="{ body: 'space-y-3' }">
        <p class="text-xs text-(--ui-text-muted)">
          سؤال <span class="num">{{ i + 1 }}</span> · <span class="num">{{ q.points }}</span> درجة
          <UIcon v-if="saving[q.id]" name="i-lucide-loader-circle" class="size-3 animate-spin" />
          <UIcon v-else-if="answers[q.id] !== undefined && answers[q.id] !== null" name="i-lucide-check" class="size-3 text-(--ui-success)" />
        </p>
        <p class="whitespace-pre-line font-bold leading-relaxed">
          {{ q.body }}
        </p>
        <div v-if="q.kind === 'mcq'" class="grid gap-2">
          <button v-for="o in q.options" :key="o.id" type="button" class="rounded-(--ui-radius) border p-3 text-start transition" :class="answers[q.id] === o.id ? 'border-primary bg-primary/10 font-bold' : 'border-(--ui-border) hover:border-(--ui-border-accented)'" @click="answer(q.id, o.id)">
            {{ o.text }}
          </button>
        </div>
        <div v-else-if="q.kind === 'true_false'" class="grid grid-cols-2 gap-2">
          <button v-for="v in [true, false]" :key="String(v)" type="button" class="rounded-(--ui-radius) border p-3 font-bold transition" :class="answers[q.id] === v ? 'border-primary bg-primary/10' : 'border-(--ui-border)'" @click="answer(q.id, v)">
            {{ v ? 'صح' : 'غلط' }}
          </button>
        </div>
        <UInput v-else-if="q.kind === 'fill'" :model-value="String(answers[q.id] ?? '')" class="w-full" placeholder="إجابتك" @update:model-value="v => answerLater(q.id, String(v))" />
        <UTextarea v-else-if="q.kind === 'essay'" :model-value="String(answers[q.id] ?? '')" :rows="4" autoresize class="w-full" placeholder="اكتب إجابتك" @update:model-value="v => answerLater(q.id, String(v))" />
        <div v-else-if="q.kind === 'photo'" class="space-y-2">
          <p class="text-sm text-(--ui-text-muted)">
            حل في الكشكول وصوّر الحل.
          </p>
          <input type="file" accept="image/*" capture="environment" class="block w-full text-sm" @change="e => uploadPhoto(q.id, e)">
          <p v-if="answers[q.id]" class="text-sm text-(--ui-success)">
            الصورة اتبعتت ✓
          </p>
        </div>
      </UCard>
      <UButton block size="xl" icon="i-lucide-send" label="سلّم الامتحان" :loading="submitting" @click="submit()" />
    </template>

    <!-- Result -->
    <template v-else-if="result">
      <UCard :ui="{ body: 'space-y-2 text-center' }">
        <p class="text-sm text-(--ui-text-muted)">
          {{ result.submitted_by === 'timer' ? 'الوقت خلص واتسلّم' : 'اتسلّم' }}
        </p>
        <p class="num text-4xl font-extrabold" :class="result.passed ? 'text-(--ui-success)' : 'text-(--ui-error)'">
          {{ result.score }} / {{ result.max_score }}
        </p>
        <p class="num font-bold">
          {{ result.percent }}%
        </p>
        <p v-if="result.pending_grading" class="text-sm text-(--ui-warning)">
          فيه <span class="num">{{ result.pending_grading }}</span> إجابة بيصحّحها المدرس، ودرجتك ممكن تزيد.
        </p>
        <p v-if="!result.answers_visible" class="text-sm text-(--ui-text-muted)">
          الإجابات الصح هتظهر {{ result.answers_at ? `بعد ${formatDate(result.answers_at, true)}` : 'لما المدرس يفتحها' }}.
        </p>
        <UButton color="neutral" variant="ghost" label="رجوع" @click="result = null; loadOverview()" />
      </UCard>
      <UCard v-for="(r, i) in result.review ?? []" :key="r.id" :ui="{ body: 'space-y-2' }">
        <p class="flex items-center gap-2 text-xs">
          <UIcon :name="r.correct === null ? 'i-lucide-hourglass' : r.correct ? 'i-lucide-circle-check' : 'i-lucide-circle-x'" class="size-4" :class="r.correct === null ? 'text-(--ui-warning)' : r.correct ? 'text-(--ui-success)' : 'text-(--ui-error)'" />
          سؤال <span class="num">{{ i + 1 }}</span> · <span class="num">{{ r.points_awarded }}</span> من <span class="num">{{ r.points }}</span>
        </p>
        <p class="font-bold">
          {{ r.body }}
        </p>
        <p class="text-sm">
          إجابتك: <b>{{ show(r, r.your_answer) }}</b>
        </p>
        <p v-if="!r.correct && r.correct_answer !== null" class="text-sm text-(--ui-success)">
          الصح: <b>{{ show(r, r.correct_answer) }}</b>
        </p>
        <p v-if="r.comment" class="rounded-(--ui-radius) bg-primary/10 p-2 text-sm">
          تعليق المدرس: {{ r.comment }}
        </p>
        <p v-if="r.explanation" class="text-sm text-(--ui-text-muted)">
          {{ r.explanation }}
        </p>
      </UCard>
    </template>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ auth: true })

type Overview = { lesson_id: string, title: string, ready: boolean, duration_minutes: number | null, opens_at: string | null, closes_at: string | null, attempts_allowed: number | null, attempts_used: number, questions_count: number, max_score: number, in_progress: string | null, attempts: { id: string, number: number, score: number, max_score: number, percent: number, passed: boolean }[], locked: string | null, server_now: string }
type Q = { id: string, kind: 'mcq' | 'true_false' | 'fill' | 'essay' | 'photo', body: string, options: { id: string, text: string }[] | null, points: number, answer: unknown }
type Paper = { id: string, status: string, ends_at: string, server_now: string, questions: Q[] }
type Review = { id: string, kind: Q['kind'], body: string, options: { id: string, text: string }[] | null, points: number, your_answer: unknown, correct: boolean | null, correct_answer: unknown, points_awarded: number, comment: string | null, explanation: string | null }
type Result = { score: number, max_score: number, percent: number, passed: boolean, submitted_by: string, pending_grading: number, answers_visible: boolean, answers_at: string | null, review: Review[] | null }

const api = useLearnApi()
const lesson = String(useRoute().params.lesson)
const overview = ref<Overview | null>(null)
const paper = ref<Paper | null>(null)
const result = ref<Result | null>(null)
const error = ref<string | null>(null)
const starting = ref(false)
const submitting = ref(false)
const answers = reactive<Record<string, unknown>>({})
const saving = reactive<Record<string, boolean>>({})

async function loadOverview() {
  overview.value = (await api<{ data: Overview }>(`/learner/exams/${lesson}`)).data
}
onMounted(loadOverview)

// The server's clock: remaining = ends_at − (now + offset).
const offset = ref(0)
const now = ref(Date.now())
let tick: ReturnType<typeof setInterval> | undefined
const remaining = computed(() => paper.value ? Math.max(0, Math.floor((Date.parse(paper.value.ends_at) - (now.value + offset.value)) / 1000)) : 0)
const clock = computed(() => `${Math.floor(remaining.value / 60)}:${String(remaining.value % 60).padStart(2, '0')}`)
const answeredCount = computed(() => paper.value?.questions.filter(q => answers[q.id] !== undefined && answers[q.id] !== null && answers[q.id] !== '').length ?? 0)

watch(remaining, (left) => {
  if (paper.value && left === 0) submit(true)
})

async function start() {
  starting.value = true
  error.value = null
  try {
    const p = (await api<{ data: Paper }>(`/learner/exams/${lesson}/start`, { method: 'POST' })).data
    offset.value = Date.parse(p.server_now) - Date.now()
    p.questions.forEach(q => (answers[q.id] = q.answer ?? undefined))
    paper.value = p
    tick = setInterval(() => (now.value = Date.now()), 1000)
  }
  catch (e) {
    error.value = apiMessage(e)
  }
  finally {
    starting.value = false
  }
}

async function save(question: string, value: unknown) {
  if (!paper.value) return
  saving[question] = true
  try {
    await api(`/learner/attempts/${paper.value.id}/answers/${question}`, { method: 'PUT', body: { answer: value } })
  }
  catch (e) {
    if (apiCode(e) === 'attempt_closed') await finish()
  }
  finally {
    saving[question] = false
  }
}
function answer(question: string, value: unknown) {
  answers[question] = value
  save(question, value)
}
const timers: Record<string, ReturnType<typeof setTimeout>> = {}
function answerLater(question: string, value: string) {
  answers[question] = value
  clearTimeout(timers[question])
  timers[question] = setTimeout(() => save(question, value), 700)
}

async function uploadPhoto(question: string, e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file || !paper.value) return
  saving[question] = true
  const body = new FormData()
  body.append('photo', file)
  try {
    await api(`/learner/attempts/${paper.value.id}/answers/${question}/photo`, { method: 'POST', body })
    answers[question] = { photo: true }
  }
  catch (err) {
    error.value = apiMessage(err)
  }
  finally {
    saving[question] = false
  }
}

async function submit(auto = false) {
  if (!paper.value) return
  if (!auto && answeredCount.value < paper.value.questions.length && !confirm('فيه أسئلة ما جاوبتش عليها. تسلّم برضه؟')) return
  submitting.value = true
  await Promise.all(Object.values(timers).map(clearTimeout))
  try {
    result.value = (await api<{ data: Result }>(`/learner/attempts/${paper.value.id}/submit`, { method: 'POST' })).data
  }
  catch {
    await finish()
  }
  finally {
    clearInterval(tick)
    paper.value = null
    submitting.value = false
  }
}
/** The server already submitted it (time up): show the result. */
async function finish() {
  if (!paper.value) return
  const id = paper.value.id
  clearInterval(tick)
  paper.value = null
  await showResult(id)
}
async function showResult(attempt: string) {
  result.value = (await api<{ data: Result }>(`/learner/attempts/${attempt}/result`)).data
}

// Leaving the page during the exam is recorded.
function onVisibility() {
  if (document.visibilityState === 'hidden' && paper.value) {
    api(`/learner/attempts/${paper.value.id}/leave`, { method: 'POST' }).catch(() => {})
  }
}
onMounted(() => document.addEventListener('visibilitychange', onVisibility))
onBeforeUnmount(() => {
  document.removeEventListener('visibilitychange', onVisibility)
  clearInterval(tick)
})

function show(r: Review, value: unknown): string {
  if (value === null || value === undefined) return '—'
  if (r.kind === 'mcq') return r.options?.find(o => o.id === value)?.text ?? '—'
  if (r.kind === 'true_false') return value ? 'صح' : 'غلط'
  if (Array.isArray(value)) return value.join(' / ')
  if (typeof value === 'object') return 'صورة'
  return String(value)
}
</script>
