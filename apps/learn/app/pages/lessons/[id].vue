<template>
  <div class="space-y-4">
    <NuxtLink v-if="courseId" :to="`/courses/${courseId}`" class="inline-flex items-center gap-1 text-sm text-(--ui-text-muted) hover:underline">
      <UIcon name="i-lucide-arrow-right" class="size-4" /> الكورس
    </NuxtLink>

    <UAlert v-if="error" color="error" variant="subtle" :title="error" :actions="courseId ? [{ label: 'رجوع للكورس', to: `/courses/${courseId}` }] : []" />
    <USkeleton v-else-if="!opened" class="aspect-video w-full rounded-xl" />

    <template v-else-if="opened.kind === 'video' && opened.playback">
      <VideoPlayer ref="player" :key="opened.session_id" :src="opened.playback.url" :poster="opened.playback.poster" :start-at="opened.resume_at ?? 0" :watermark="watermark" :ended="ended" @reopen="open" @broken="ended = BROKEN" />
      <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
        <h1 class="text-xl font-bold">
          {{ title }}
        </h1>
        <span v-if="viewsLeft !== null" class="text-(--ui-text-muted)">فاضل <span class="num font-bold">{{ viewsLeft }}</span> مشاهدة بعد دي</span>
      </div>
    </template>

    <template v-else-if="opened.kind === 'pdf' && opened.file_url">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <h1 class="text-xl font-bold">
          {{ title }}
        </h1>
        <UButton :to="opened.file_url" target="_blank" color="neutral" variant="outline" icon="i-lucide-external-link" label="افتح في صفحة لوحدها" />
      </div>
      <iframe :src="opened.file_url" class="h-[75vh] w-full rounded-xl border border-(--ui-border) bg-white" title="الملزمة" />
      <p class="text-xs text-(--ui-text-muted)">
        اللينك ده بيقفل بعد دقايق. لو قفل، افتح الدرس تاني.
      </p>
    </template>

    <LessonQuestions v-if="opened" :lesson-id="id" :position="() => player?.state().position ?? null" />
  </div>
</template>

<script setup lang="ts">
import type { CourseOutline } from '~/utils/types'

definePageMeta({ auth: true })

type Opened = {
  kind: 'video' | 'pdf' | 'exam' | 'live'
  session_id?: string
  playback?: { url: string, expires_at: string, poster?: string | null }
  resume_at?: number
  views_left?: number | null
  beat_seconds?: number
  watermark?: { text: string, issued_at: string, signature: string }
  file_url?: string
}

const route = useRoute()
const api = useLearnApi()
const config = useRuntimeConfig().public
const token = useState<string | null>('learner-token')
const id = String(route.params.id)
const courseId = typeof route.query.course === 'string' ? route.query.course : null

const opened = ref<Opened | null>(null)
const error = ref<string | null>(null)
const ended = ref<string | null>(null)
const watermark = ref<{ text: string } | null>(null)
const viewsLeft = ref<number | null>(null)
const player = ref<{ state: () => { position: number, playing: boolean }, refresh: (src: string) => void } | null>(null)
let beat: ReturnType<typeof setInterval> | undefined

const { data: outline } = await useAsyncData(`outline-${courseId}`, async () => courseId ? (await api<{ data: CourseOutline }>(`/learner/courses/${courseId}`)).data : null)
const title = computed(() => outline.value?.chapters.flatMap(c => c.lessons).find(l => l.id === id)?.title ?? '')

// The player lost the stream (a newer session elsewhere, or a stale link): the student chooses to continue here.
const BROKEN = 'الفيديو وقف. لو فتحته من جهاز أو تاب تاني، اتقفل هنا.'

async function open() {
  clearInterval(beat)
  ended.value = null
  error.value = null
  try {
    const res = (await api<{ data: Opened }>(`/learner/lessons/${id}/open`, { method: 'POST' })).data
    if (res.kind === 'live') {
      await navigateTo(`/live/${id}`, { replace: true })
      return
    }
    if (res.kind === 'exam') {
      await navigateTo(`/exams/${id}`, { replace: true })
      return
    }
    opened.value = res
    watermark.value = res.watermark ?? null
    viewsLeft.value = res.views_left ?? null
    if (res.kind === 'video') {
      beat = setInterval(heartbeat, (res.beat_seconds ?? 60) * 1000)
    }
  }
  catch (e) {
    error.value = apiMessage(e)
  }
}

async function heartbeat() {
  const session = opened.value?.session_id
  if (!session || !player.value) return
  try {
    const res = (await api<{ data: { playback: { url: string } | null, views_left: number | null, watermark: { text: string } } }>(`/learner/playback/${session}/beat`, { method: 'POST', body: player.value.state() })).data
    watermark.value = res.watermark
    viewsLeft.value = res.views_left
    if (res.playback) player.value.refresh(res.playback.url)
  }
  catch (e) {
    if (apiCode(e) === 'playback_ended') {
      clearInterval(beat)
      ended.value = apiMessage(e)
    }
  }
}

/** The session ends with the page (keepalive survives closing the tab). */
function stop() {
  const session = opened.value?.session_id
  if (!session || ended.value) return
  fetch(`${config.apiBase}/learner/playback/${session}/stop`, { method: 'POST', keepalive: true, headers: { Accept: 'application/json', Authorization: `Bearer ${token.value}` } }).catch(() => {})
}

onMounted(() => {
  open()
  window.addEventListener('pagehide', stop)
})
onBeforeUnmount(() => {
  clearInterval(beat)
  stop()
  window.removeEventListener('pagehide', stop)
})
</script>
