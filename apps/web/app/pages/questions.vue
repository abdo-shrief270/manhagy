<template>
  <div class="max-w-4xl space-y-6">
    <PageHeader title="أسئلة الطلاب" description="الطلاب بيسألوا تحت الدرس. ردّك يوصل للطالب، ولو خليته «للكل» يظهر لكل اللي بيتفرّجوا على الدرس.">
      <div class="flex gap-1 rounded-(--ui-radius) bg-(--ui-bg-elevated) p-1">
        <UButton v-for="s in statuses" :key="s.value" size="sm" :color="status === s.value ? 'primary' : 'neutral'" :variant="status === s.value ? 'solid' : 'ghost'" @click="status = s.value">
          {{ s.label }}
          <UBadge v-if="s.value === 'open' && data?.meta.open" size="sm" color="warning" class="num" :label="String(data.meta.open)" />
        </UButton>
      </div>
    </PageHeader>

    <p v-if="!rows.length" class="app-card p-8 text-center text-sm text-(--ui-text-muted)">
      {{ status === 'open' ? 'مفيش أسئلة مستنية رد.' : 'مفيش.' }}
    </p>
    <UCard v-for="q in rows" :key="q.id" :ui="{ body: 'space-y-3' }">
      <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
        <span class="font-bold">{{ q.learner_name }}</span>
        <span class="text-(--ui-text-muted)">
          <NuxtLink v-if="q.course_id" :to="`/courses/${q.course_id}`" class="hover:underline">{{ q.lesson_title }}</NuxtLink>
          <template v-if="q.position !== null"> · عند <span class="num">{{ formatDuration(q.position) }}</span></template>
          · {{ timeAgo(q.created_at) }}
        </span>
      </div>
      <p class="whitespace-pre-line leading-relaxed">
        {{ q.body }}
      </p>
      <div v-if="q.status === 'answered'" class="rounded-(--ui-radius) bg-(--app-primary-soft) p-3 text-sm">
        <p class="whitespace-pre-line">
          {{ q.answer }}
        </p>
        <p class="mt-1 text-xs text-(--ui-text-muted)">
          {{ q.answered_by_name }} · {{ formatDate(q.answered_at, true) }}<template v-if="q.public"> · ظاهر للكل</template>
        </p>
      </div>
      <div v-if="q.status !== 'hidden'" class="space-y-2">
        <UTextarea v-model="answers[q.id]" :rows="2" autoresize class="w-full" :placeholder="q.status === 'answered' ? 'عدّل الرد…' : 'اكتب ردك…'" />
        <div class="flex flex-wrap items-center gap-2">
          <UCheckbox v-model="publicFor[q.id]" label="اعرض الرد لكل طلاب الدرس" />
          <UButton class="ms-auto" size="sm" color="neutral" variant="ghost" label="اخفي السؤال" @click="hide(q)" />
          <UButton size="sm" icon="i-lucide-send" label="رد" :disabled="!answers[q.id]?.trim()" :loading="busy === q.id" @click="answer(q)" />
        </div>
      </div>
    </UCard>
  </div>
</template>

<script setup lang="ts">
import type { LessonQuestion } from '~/types/manhagy'

definePageMeta({ permission: 'engagement.answer' })

const statuses = [{ value: 'open', label: 'مستنية رد' }, { value: 'answered', label: 'اتردّ عليها' }, { value: 'hidden', label: 'مخفية' }] as const
const api = useApi()
const toast = useToast()
const status = ref<LessonQuestion['status']>('open')
const { data, refresh } = await useAsyncData('lesson-questions', () => api<{ data: LessonQuestion[], meta: { open: number } }>('/engagement/questions', { query: { status: status.value } }), { watch: [status] })
const rows = computed(() => data.value?.data ?? [])
const answers = reactive<Record<string, string>>({})
const publicFor = reactive<Record<string, boolean>>({})
const busy = ref<string | null>(null)

watch(rows, list => list.forEach((q) => {
  answers[q.id] ??= q.answer ?? ''
  publicFor[q.id] ??= q.public
}), { immediate: true })

async function answer(q: LessonQuestion) {
  busy.value = q.id
  try {
    await api(`/engagement/questions/${q.id}/answer`, { method: 'POST', body: { answer: answers[q.id], public: publicFor[q.id] ?? false } })
    await refresh()
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    busy.value = null
  }
}
async function hide(q: LessonQuestion) {
  await api(`/engagement/questions/${q.id}/hide`, { method: 'POST' }).catch(() => {})
  await refresh()
}
</script>
