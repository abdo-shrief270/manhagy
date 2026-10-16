<template>
  <div v-if="state?.offered" class="app-card space-y-3 p-4">
    <div class="flex items-center gap-2">
      <UIcon name="i-lucide-award" class="size-6 text-primary" />
      <h2 class="flex-1 font-bold">
        شهادة الكورس
      </h2>
    </div>
    <template v-if="state.certificate">
      <p class="text-sm">
        مبروك! خدت الشهادة يوم {{ formatDate(state.certificate.issued_at) }}.
      </p>
      <div class="flex flex-wrap gap-2">
        <UButton icon="i-lucide-award" label="افتح الشهادة" :to="`/certificates/${state.certificate.code}`" />
        <UButton color="success" variant="soft" icon="i-lucide-message-circle" label="ابعتها" :to="`https://wa.me/?text=${encodeURIComponent(`شهادتي في «${state.certificate.course_title}»: ${state.certificate.url}`)}`" target="_blank" />
      </div>
    </template>
    <template v-else>
      <p class="text-sm text-(--ui-text-muted)">
        خلّص كل الدروس<template v-if="state.min_exam_percent !== null">
          وانجح في كل امتحان بـ <span class="num">{{ state.min_exam_percent }}%</span> على الأقل
        </template>
        وخد شهادة بكود تحقق.
      </p>
      <UProgress :model-value="state.total ? Math.round(state.completed / state.total * 100) : 0" />
      <p class="num text-xs text-(--ui-text-muted)">
        {{ state.completed }} من {{ state.total }} درس
      </p>
      <ul v-if="state.min_exam_percent !== null && state.exams.length" class="space-y-1 text-sm">
        <li v-for="e in state.exams" :key="e.lesson_id" class="flex items-center gap-2">
          <UIcon :name="e.passed ? 'i-lucide-circle-check' : 'i-lucide-circle'" class="size-4" :class="e.passed ? 'text-(--ui-success)' : 'text-(--ui-text-muted)'" />
          <span class="flex-1">{{ e.title }}</span>
          <span class="num text-(--ui-text-muted)">{{ e.best === null ? '—' : `${e.best}%` }}</span>
        </li>
      </ul>
      <UButton :disabled="!state.eligible" :loading="claiming" icon="i-lucide-award" label="خد الشهادة" @click="claim" />
    </template>
  </div>
</template>

<script setup lang="ts">
interface CertificateStatus {
  offered: boolean
  min_exam_percent: number | null
  completed: number
  total: number
  exams: { lesson_id: string, title: string, best: number | null, passed: boolean }[]
  eligible: boolean
  certificate: { code: string, course_title: string, issued_at: string, url: string } | null
}

const props = defineProps<{ courseId: string }>()
const api = useLearnApi()
const toast = useToast()
const state = ref<CertificateStatus | null>(null)
const claiming = ref(false)

onMounted(async () => {
  state.value = (await api<{ data: CertificateStatus }>(`/learner/courses/${props.courseId}/certificate`)).data
})

async function claim() {
  claiming.value = true
  try {
    state.value = (await api<{ data: CertificateStatus }>(`/learner/courses/${props.courseId}/certificate`, { method: 'POST' })).data
    toast.add({ color: 'success', title: 'مبروك على الشهادة 🎉' })
  }
  catch (e) {
    toast.add({ color: 'error', title: apiMessage(e) })
  }
  finally {
    claiming.value = false
  }
}
</script>
