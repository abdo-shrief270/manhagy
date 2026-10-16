<template>
  <UCard :ui="{ body: 'space-y-4' }">
    <template #header>
      <h2 class="font-bold">
        أسئلة على الدرس
      </h2>
    </template>
    <form class="space-y-2" @submit.prevent="ask">
      <UTextarea v-model="body" :rows="2" autoresize class="w-full" placeholder="مش فاهم حاجة؟ اسأل هنا والمدرس هيرد عليك." />
      <div class="flex justify-end">
        <UButton type="submit" size="sm" icon="i-lucide-send" label="ابعت السؤال" :disabled="body.trim().length < 3" :loading="sending" />
      </div>
    </form>
    <div v-for="q in questions" :key="q.id" class="space-y-2 rounded-(--ui-radius) border border-(--ui-border) p-3 text-sm">
      <p class="text-xs text-(--ui-text-muted)">
        {{ q.mine ? 'سؤالك' : q.asked_by }}<template v-if="q.position !== null"> · عند <span class="num">{{ formatDuration(q.position) || '0:00' }}</span></template>
      </p>
      <p class="whitespace-pre-line">
        {{ q.body }}
      </p>
      <p v-if="q.answer" class="whitespace-pre-line rounded-(--ui-radius) bg-primary/10 p-2">
        <b>{{ q.answered_by_name ?? 'المدرس' }}:</b> {{ q.answer }}
      </p>
      <p v-else class="text-xs text-(--ui-text-muted)">
        مستني رد…
      </p>
    </div>
  </UCard>
</template>

<script setup lang="ts">
const props = defineProps<{ lessonId: string, position: () => number | null }>()
type Q = { id: string, body: string, position: number | null, answer: string | null, answered_by_name: string | null, mine: boolean, asked_by: string | null }

const api = useLearnApi()
const toast = useToast()
const body = ref('')
const sending = ref(false)
const { data: questions, refresh } = await useAsyncData(`lesson-questions-${props.lessonId}`, async () => (await api<{ data: Q[] }>(`/learner/lessons/${props.lessonId}/questions`)).data)

async function ask() {
  sending.value = true
  try {
    await api(`/learner/lessons/${props.lessonId}/questions`, { method: 'POST', body: { body: body.value, position: props.position() } })
    body.value = ''
    await refresh()
  }
  catch (e) {
    toast.add({ color: 'error', title: apiMessage(e) })
  }
  finally {
    sending.value = false
  }
}
</script>
