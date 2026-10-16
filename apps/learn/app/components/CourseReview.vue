<template>
  <div v-if="state?.can_review" class="app-card space-y-3 p-4">
    <h2 class="font-bold">
      {{ state.review ? 'تقييمك للكورس' : 'قيّم الكورس' }}
    </h2>
    <p class="text-sm text-(--ui-text-muted)">
      رأيك بيساعد طلاب تانيين يختاروا، وبيظهر باسمك الأول بس.
    </p>
    <div class="flex gap-1" role="radiogroup" aria-label="التقييم">
      <button
        v-for="n in 5"
        :key="n"
        type="button"
        role="radio"
        :aria-checked="rating === n"
        :aria-label="`${n} من 5`"
        class="text-3xl leading-none transition"
        :class="n <= rating ? 'text-amber-500' : 'text-(--ui-text-dimmed)'"
        @click="rating = n"
      >
        ★
      </button>
    </div>
    <UTextarea v-model="comment" :rows="2" autoresize maxlength="1000" class="w-full" placeholder="إيه اللي عجبك؟ إيه اللي ممكن يبقى أحسن؟ (اختياري)" />
    <UButton :disabled="!rating" :loading="saving" :label="state.review ? 'عدّل تقييمك' : 'ابعت التقييم'" @click="save" />
  </div>
</template>

<script setup lang="ts">
const props = defineProps<{ courseId: string }>()
const api = useLearnApi()
const toast = useToast()
const state = ref<{ can_review: boolean, review: { rating: number, comment: string | null } | null } | null>(null)
const rating = ref(0)
const comment = ref('')
const saving = ref(false)

onMounted(async () => {
  state.value = (await api<{ data: NonNullable<typeof state.value> }>(`/learner/courses/${props.courseId}/review`)).data
  rating.value = state.value.review?.rating ?? 0
  comment.value = state.value.review?.comment ?? ''
})

async function save() {
  saving.value = true
  try {
    const saved = (await api<{ data: { rating: number, comment: string | null } }>(`/learner/courses/${props.courseId}/review`, { method: 'PUT', body: { rating: rating.value, comment: comment.value || null } })).data
    if (state.value) state.value.review = saved
    toast.add({ color: 'success', title: 'شكراً على تقييمك' })
  }
  catch (e) {
    toast.add({ color: 'error', title: apiMessage(e) })
  }
  finally {
    saving.value = false
  }
}
</script>
