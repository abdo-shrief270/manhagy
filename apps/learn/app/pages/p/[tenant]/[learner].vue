<template>
  <div class="space-y-4">
    <USkeleton v-if="!report && !error" class="h-64" />
    <UAlert v-else-if="error" color="error" variant="subtle" title="اللينك ده انتهى أو مش صحيح" description="اطلب من المدرس يبعتلك تقرير جديد." />
    <template v-else-if="report">
      <div class="app-card space-y-1 p-5">
        <p class="text-sm text-(--ui-text-muted)">
          تقرير آخر <span class="num">{{ report.days }}</span> يوم · {{ report.teacher }}
        </p>
        <h1 class="text-2xl font-extrabold">
          {{ report.student }}
        </h1>
        <p v-if="report.grade" class="text-sm text-(--ui-text-muted)">
          {{ report.grade }}
        </p>
      </div>
      <div class="grid grid-cols-3 gap-3 text-center">
        <div class="app-card p-3">
          <p class="num text-2xl font-extrabold">
            {{ report.watched.length }}
          </p>
          <p class="text-xs text-(--ui-text-muted)">
            درس اتفرّج عليه
          </p>
        </div>
        <div class="app-card p-3">
          <p class="num text-2xl font-extrabold">
            {{ report.watched_minutes }}
          </p>
          <p class="text-xs text-(--ui-text-muted)">
            دقيقة مشاهدة
          </p>
        </div>
        <div class="app-card p-3">
          <p class="num text-2xl font-extrabold" :class="report.missing.length ? 'text-(--ui-warning)' : ''">
            {{ report.missing.length }}
          </p>
          <p class="text-xs text-(--ui-text-muted)">
            لسه ما خلّصهوش
          </p>
        </div>
      </div>
      <UCard v-if="report.exams.length" :ui="{ body: 'p-0 sm:p-0' }">
        <template #header>
          <h2 class="font-bold">
            الامتحانات
          </h2>
        </template>
        <div v-for="(e, i) in report.exams" :key="i" class="flex items-center gap-3 border-b border-(--ui-border) px-4 py-3 text-sm last:border-0">
          <span class="flex-1">{{ e.title }}</span>
          <span class="num font-bold">{{ e.score }} / {{ e.max_score }}</span>
          <UBadge :color="e.percent >= 50 ? 'success' : 'error'" variant="subtle" class="num" :label="`${e.percent}%`" />
        </div>
      </UCard>
      <UCard v-if="report.watched.length" :ui="{ body: 'p-0 sm:p-0' }">
        <template #header>
          <h2 class="font-bold">
            اللي اتفرّج عليه
          </h2>
        </template>
        <div v-for="(w, i) in report.watched" :key="i" class="flex items-center gap-3 border-b border-(--ui-border) px-4 py-3 text-sm last:border-0">
          <UIcon name="i-lucide-circle-check" class="size-4 text-(--ui-success)" />
          <span class="flex-1">{{ w.title }}</span>
          <span class="num text-(--ui-text-muted)">{{ w.minutes }} د</span>
        </div>
      </UCard>
      <UCard v-if="report.missing.length" :ui="{ body: 'p-0 sm:p-0' }">
        <template #header>
          <h2 class="font-bold">
            لسه ما خلّصهوش
          </h2>
        </template>
        <div v-for="(m, i) in report.missing" :key="i" class="flex items-center gap-3 border-b border-(--ui-border) px-4 py-3 text-sm last:border-0">
          <UIcon name="i-lucide-circle-dashed" class="size-4 text-(--ui-text-muted)" />
          <span class="flex-1">{{ m.title }}</span>
        </div>
      </UCard>
      <p class="text-center text-xs text-(--ui-text-muted)">
        منهجي · التقرير ده خاص بيك، ما تبعتهوش لحد.
      </p>
    </template>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'plain', parent: true })

type Report = { student: string, grade: string | null, teacher: string, days: number, watched: { title: string, minutes: number }[], watched_minutes: number, missing: { title: string }[], exams: { title: string, score: number, max_score: number, percent: number }[] }

const route = useRoute()
const api = useLearnApi()
const report = ref<Report | null>(null)
const error = ref(false)
onMounted(async () => {
  try {
    const query = new URLSearchParams(route.query as Record<string, string>).toString()
    report.value = (await api<{ data: Report }>(`/public/parent-report/${route.params.tenant}/${route.params.learner}?${query}`)).data
  }
  catch {
    error.value = true
  }
})
useHead({ title: 'تقرير الطالب' })
</script>
