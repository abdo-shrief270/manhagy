<template>
  <div class="max-w-5xl space-y-6">
    <PageHeader title="بنك الأسئلة" description="أسئلة كل كورس، ومنها بتعمل الامتحانات. الاختيار من متعدد والصح والغلط والأكمل بتتصحّح لوحدها.">
      <UButton v-if="courseId" icon="i-lucide-plus" label="سؤال جديد" @click="edit(null)" />
    </PageHeader>

    <div class="flex flex-wrap items-center gap-2">
      <USelect v-model="courseId" :items="courseItems" class="w-full sm:w-80" placeholder="اختار الكورس" />
      <USelect v-model="kind" :items="[{ value: '', label: 'كل الأنواع' }, ...Object.entries(QUESTION_KINDS).map(([value, k]) => ({ value, label: k.label }))]" class="w-44" />
      <span v-if="questions" class="ms-auto text-sm text-(--ui-text-muted)"><span class="num font-bold">{{ questions.length }}</span> سؤال</span>
    </div>

    <p v-if="!courseId" class="app-card p-8 text-center text-sm text-(--ui-text-muted)">
      اختار كورس عشان تشوف أسئلته.
    </p>
    <p v-else-if="!questions?.length" class="app-card p-8 text-center text-sm text-(--ui-text-muted)">
      لسه مفيش أسئلة في الكورس ده.
    </p>
    <div v-else class="space-y-2">
      <div v-for="(q, i) in questions" :key="q.id" class="app-card flex gap-3 p-4" :class="{ 'opacity-50': !q.active }">
        <span class="num grid size-8 shrink-0 place-items-center rounded-full bg-(--ui-bg-elevated) text-sm font-bold">{{ i + 1 }}</span>
        <div class="min-w-0 flex-1 space-y-1">
          <p class="whitespace-pre-line font-bold">
            {{ q.body }}
          </p>
          <ul v-if="q.kind === 'mcq'" class="grid gap-1 text-sm sm:grid-cols-2">
            <li v-for="o in q.options" :key="o.id" class="flex items-center gap-1.5" :class="o.id === q.answer ? 'font-bold text-(--ui-success)' : 'text-(--ui-text-muted)'">
              <UIcon :name="o.id === q.answer ? 'i-lucide-circle-check' : 'i-lucide-circle'" class="size-4" />
              {{ o.text }}
            </li>
          </ul>
          <p v-else class="text-sm text-(--ui-success)">
            {{ answerText(q) }}
          </p>
          <div class="flex flex-wrap gap-1">
            <UBadge size="sm" color="neutral" variant="subtle" :icon="QUESTION_KINDS[q.kind].icon" :label="QUESTION_KINDS[q.kind].label" />
            <UBadge size="sm" color="neutral" variant="subtle" :label="`${q.points} درجة`" />
            <UBadge size="sm" color="neutral" variant="subtle" :label="DIFFICULTY[q.difficulty]" />
            <UBadge v-if="!q.active" size="sm" color="warning" variant="subtle" label="متقفل" />
          </div>
        </div>
        <div class="flex shrink-0 flex-col gap-1">
          <UButton size="xs" color="neutral" variant="ghost" icon="i-lucide-pencil" aria-label="تعديل" @click="edit(q)" />
          <UButton v-if="q.active" size="xs" color="error" variant="ghost" icon="i-lucide-trash-2" aria-label="مسح" @click="remove(q)" />
        </div>
      </div>
    </div>

    <QuestionForm v-model:open="formOpen" :question="editing" :course-id="courseId" :chapters="chapters" @saved="() => refresh()" />
  </div>
</template>

<script setup lang="ts">
import type { CourseDetail, CourseSummary, Question } from '~/types/manhagy'

definePageMeta({ permission: 'assessments.manage' })

const api = useApi()
const toast = useToast()
const route = useRoute()
const { data: courses } = await useAsyncData('courses-for-bank', async () => (await api<{ data: CourseSummary[] }>('/courses')).data)
const courseItems = computed(() => (courses.value ?? []).map(c => ({ value: c.id, label: c.title })))
const courseId = ref(typeof route.query.course === 'string' ? route.query.course : (courses.value?.[0]?.id ?? ''))
const kind = ref('')

const { data: questions, refresh } = await useAsyncData('questions', async () => courseId.value
  ? (await api<{ data: Question[] }>('/assessments/questions', { query: { course_id: courseId.value, kind: kind.value || undefined } })).data
  : [], { watch: [courseId, kind] })
const { data: course } = await useAsyncData('bank-course', async () => courseId.value ? (await api<{ data: CourseDetail }>(`/courses/${courseId.value}`)).data : null, { watch: [courseId] })
const chapters = computed(() => course.value?.chapters.map(c => ({ id: c.id, title: c.title })) ?? [])

const formOpen = ref(false)
const editing = ref<Question | null>(null)
function edit(q: Question | null) {
  editing.value = q
  formOpen.value = true
}
async function remove(q: Question) {
  if (!confirm('تمسح السؤال ده؟ لو في امتحان أو حد جاوب عليه، هيتقفل بس.')) return
  try {
    await api(`/assessments/questions/${q.id}`, { method: 'DELETE' })
    await refresh()
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
}
</script>
