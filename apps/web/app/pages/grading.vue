<template>
  <div class="max-w-4xl space-y-6">
    <PageHeader title="التصحيح" description="الإجابات المقالية وصور الحلول، الأقدم الأول. الدرجة بتتحسب للطالب على طول.">
      <div class="flex gap-1 rounded-(--ui-radius) bg-(--ui-bg-elevated) p-1">
        <UButton size="sm" :color="!graded ? 'primary' : 'neutral'" :variant="!graded ? 'solid' : 'ghost'" @click="graded = false">
          مستنية
          <UBadge v-if="data?.meta.waiting" size="sm" color="warning" class="num" :label="String(data.meta.waiting)" />
        </UButton>
        <UButton size="sm" :color="graded ? 'primary' : 'neutral'" :variant="graded ? 'solid' : 'ghost'" label="اتصحّحت" @click="graded = true" />
      </div>
    </PageHeader>

    <p v-if="!rows.length" class="app-card p-8 text-center text-sm text-(--ui-text-muted)">
      {{ graded ? 'لسه ما صحّحتش حاجة.' : 'مفيش إجابات مستنية تصحيح 🎉' }}
    </p>
    <UCard v-for="item in rows" :key="item.id" :ui="{ body: 'space-y-3' }">
      <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
        <span class="font-bold">{{ item.learner_name }}</span>
        <span class="text-(--ui-text-muted)">{{ item.exam }} · {{ timeAgo(item.submitted_at) }}</span>
      </div>
      <p class="whitespace-pre-line rounded-(--ui-radius) bg-(--ui-bg-elevated) p-3 text-sm font-bold">
        {{ item.question }}
      </p>
      <p v-if="item.kind === 'essay'" class="whitespace-pre-line leading-relaxed">
        {{ item.answer || '—' }}
      </p>
      <img v-else-if="photos[item.id]" :src="photos[item.id]" alt="حل الطالب" class="max-h-[60vh] rounded-lg border border-(--ui-border)">
      <p v-if="item.model_answer" class="text-sm text-(--ui-text-muted)">
        <b>النموذج:</b> {{ item.model_answer }}
      </p>
      <div class="flex flex-wrap items-end gap-2 border-t border-(--ui-border) pt-3">
        <div class="flex gap-1">
          <UButton v-for="p in pointOptions(item.max_points)" :key="p" size="sm" class="num w-10 justify-center" :color="draft[item.id]?.points === p ? 'primary' : 'neutral'" :variant="draft[item.id]?.points === p ? 'solid' : 'outline'" :label="String(p)" @click="setDraft(item, { points: p })" />
        </div>
        <span class="text-sm text-(--ui-text-muted)">من <span class="num">{{ item.max_points }}</span></span>
        <UInput :model-value="draft[item.id]?.comment ?? item.comment ?? ''" class="min-w-48 flex-1" placeholder="تعليق للطالب (اختياري)" @update:model-value="v => setDraft(item, { comment: String(v) })" />
        <UButton :loading="busy === item.id" :disabled="draft[item.id]?.points === undefined" icon="i-lucide-check" label="سجّل" @click="grade(item)" />
      </div>
      <p v-if="item.graded_by_name" class="text-xs text-(--ui-text-muted)">
        صحّحه {{ item.graded_by_name }}: <span class="num">{{ item.points_awarded }}</span> من <span class="num">{{ item.max_points }}</span>
      </p>
    </UCard>
  </div>
</template>

<script setup lang="ts">
import type { GradingItem } from '~/types/manhagy'

definePageMeta({ permission: 'assessments.grade' })

const api = useApi()
const toast = useToast()
const graded = ref(false)
const { data, refresh } = await useAsyncData('grading', () => api<{ data: GradingItem[], meta: { waiting: number | null } }>('/assessments/grading', { query: { graded: graded.value ? 1 : undefined } }), { watch: [graded] })
const rows = computed(() => data.value?.data ?? [])

const photos = reactive<Record<string, string>>({})
watch(rows, async (list) => {
  for (const item of list.filter(i => i.has_photo && !photos[i.id])) {
    const blob = await api<Blob>(`/assessments/grading/${item.id}/photo`, { responseType: 'blob' }).catch(() => null)
    if (blob) photos[item.id] = URL.createObjectURL(blob)
  }
}, { immediate: true })
onBeforeUnmount(() => Object.values(photos).forEach(URL.revokeObjectURL))

const draft = reactive<Record<string, { points?: number, comment?: string }>>({})
function setDraft(item: GradingItem, patch: { points?: number, comment?: string }) {
  draft[item.id] = { points: item.points_awarded ?? undefined, ...draft[item.id], ...patch }
}
function pointOptions(max: number): number[] {
  return max <= 10 ? Array.from({ length: max + 1 }, (_, i) => i) : [0, Math.round(max / 4), Math.round(max / 2), Math.round(max * 3 / 4), max]
}

const busy = ref<string | null>(null)
async function grade(item: GradingItem) {
  const d = draft[item.id]
  if (d?.points === undefined) return
  busy.value = item.id
  try {
    await api(`/assessments/grading/${item.id}`, { method: 'POST', body: { points: d.points, comment: d.comment ?? item.comment ?? null } })
    delete draft[item.id]
    await refresh()
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    busy.value = null
  }
}
</script>
