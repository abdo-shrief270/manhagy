<template>
  <UModal v-model:open="open" :title="question ? 'تعديل السؤال' : 'سؤال جديد'" :ui="{ content: 'sm:max-w-2xl' }">
    <template #body>
      <form class="space-y-4" @submit.prevent="save">
        <div v-if="!question" class="grid grid-cols-2 gap-2 sm:grid-cols-5">
          <button v-for="(k, key) in QUESTION_KINDS" :key="key" type="button" class="flex flex-col items-center gap-1 rounded-(--ui-radius) border p-2 text-xs font-bold transition" :class="form.kind === key ? 'border-primary bg-(--app-primary-soft)' : 'border-(--ui-border)'" @click="form.kind = key">
            <UIcon :name="k.icon" class="size-5" />
            {{ k.label }}
          </button>
        </div>
        <UFormField label="السؤال">
          <UTextarea v-model="form.body" :rows="3" autoresize class="w-full" />
        </UFormField>

        <div v-if="form.kind === 'mcq'" class="space-y-2">
          <p class="text-sm font-bold">
            الاختيارات <span class="font-normal text-(--ui-text-muted)">(علّم على الصح)</span>
          </p>
          <div v-for="(_, i) in form.options" :key="i" class="flex items-center gap-2">
            <URadioGroup :model-value="String(form.correct)" :items="[{ value: String(i), label: '' }]" @update:model-value="form.correct = i" />
            <UInput v-model="form.options[i]" class="flex-1" :placeholder="`الاختيار ${i + 1}`" />
            <UButton v-if="form.options.length > 2" size="xs" color="neutral" variant="ghost" icon="i-lucide-x" aria-label="شيل" @click="removeOption(i)" />
          </div>
          <UButton v-if="form.options.length < 6" size="xs" variant="ghost" icon="i-lucide-plus" label="اختيار" @click="form.options.push('')" />
        </div>
        <div v-else-if="form.kind === 'true_false'" class="flex gap-2">
          <UButton :color="form.tf ? 'success' : 'neutral'" :variant="form.tf ? 'solid' : 'outline'" label="صح" @click="form.tf = true" />
          <UButton :color="!form.tf ? 'error' : 'neutral'" :variant="!form.tf ? 'solid' : 'outline'" label="غلط" @click="form.tf = false" />
        </div>
        <UFormField v-else-if="form.kind === 'fill'" label="الإجابات المقبولة" description="سطر لكل طريقة كتابة. الهمزات والتشكيل والتاء المربوطة مش بتفرق.">
          <UTextarea v-model="form.accepted" :rows="2" class="w-full" />
        </UFormField>

        <UFormField :label="QUESTION_KINDS[form.kind].manual ? 'الإجابة النموذجية (للمصحّح)' : 'شرح الإجابة'" hint="اختياري">
          <UTextarea v-model="form.explanation" :rows="2" autoresize class="w-full" />
        </UFormField>
        <div class="grid gap-3 sm:grid-cols-3">
          <UFormField label="الدرجة">
            <UInputNumber v-model="form.points" :min="1" :max="100" class="w-full" />
          </UFormField>
          <UFormField label="الصعوبة">
            <USelect v-model="form.difficulty" :items="Object.entries(DIFFICULTY).map(([value, label]) => ({ value, label }))" class="w-full" />
          </UFormField>
          <UFormField label="الفصل" hint="اختياري">
            <USelect v-model="form.chapter_id" :items="[{ value: '', label: '—' }, ...chapters.map(c => ({ value: c.id, label: c.title }))]" class="w-full" />
          </UFormField>
        </div>
        <UAlert v-if="error" color="error" variant="subtle" :title="error" />
        <div class="flex justify-end gap-2">
          <UButton color="neutral" variant="ghost" label="إلغاء" @click="open = false" />
          <UButton type="submit" :loading="saving" :disabled="!form.body.trim()" label="حفظ" />
        </div>
      </form>
    </template>
  </UModal>
</template>

<script setup lang="ts">
import type { Question, QuestionKind } from '~/types/manhagy'

const props = defineProps<{ question: Question | null, courseId: string, chapters: { id: string, title: string }[] }>()
const emit = defineEmits<{ saved: [question: Question] }>()
const open = defineModel<boolean>('open', { default: false })

const api = useApi()
const blank = () => ({ kind: 'mcq' as QuestionKind, body: '', options: ['', '', '', ''], correct: 0, tf: true, accepted: '', explanation: '', points: 1, difficulty: 'medium', chapter_id: '' })
const form = reactive(blank())
const saving = ref(false)
const error = ref<string | null>(null)

watch(open, (isOpen) => {
  if (!isOpen) return
  error.value = null
  const q = props.question
  Object.assign(form, blank(), q
    ? {
        kind: q.kind,
        body: q.body,
        options: q.options?.map(o => o.text) ?? ['', '', '', ''],
        correct: Math.max(0, q.options?.findIndex(o => o.id === q.answer) ?? 0),
        tf: q.answer === true,
        accepted: Array.isArray(q.answer) ? q.answer.join('\n') : '',
        explanation: q.explanation ?? '',
        points: q.points,
        difficulty: q.difficulty,
        chapter_id: q.chapter_id ?? '',
      }
    : {})
})

function removeOption(i: number) {
  form.options.splice(i, 1)
  if (form.correct >= form.options.length) form.correct = 0
}

async function save() {
  saving.value = true
  error.value = null
  const body: Record<string, unknown> = { body: form.body, explanation: form.explanation || null, points: form.points, difficulty: form.difficulty, chapter_id: form.chapter_id || null }
  if (form.kind === 'mcq') Object.assign(body, { options: form.options.map(o => o.trim()), correct: form.correct })
  if (form.kind === 'true_false') body.correct = form.tf
  if (form.kind === 'fill') body.accepted = form.accepted.split('\n').map(s => s.trim()).filter(Boolean)
  try {
    const res = props.question
      ? await api<{ data: Question }>(`/assessments/questions/${props.question.id}`, { method: 'PATCH', body })
      : await api<{ data: Question }>('/assessments/questions', { method: 'POST', body: { ...body, course_id: props.courseId, kind: form.kind } })
    emit('saved', res.data)
    open.value = false
  }
  catch (e) {
    error.value = apiErrorMessage(e)
  }
  finally {
    saving.value = false
  }
}
</script>
