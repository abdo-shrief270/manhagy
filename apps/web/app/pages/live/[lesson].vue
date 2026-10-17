<template>
  <div class="space-y-4">
    <USkeleton v-if="!studio" class="h-96" />
    <template v-else>
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
          <NuxtLink :to="`/courses/${studio.course_id}`" class="text-sm text-(--ui-text-muted) hover:underline">الكورس</NuxtLink>
          <h1 class="flex flex-wrap items-center gap-2 text-2xl font-extrabold">
            {{ studio.title }}
            <UBadge :color="STATUS[studio.status].color" :variant="studio.status === 'live' ? 'solid' : 'subtle'" :icon="studio.status === 'live' ? 'i-lucide-radio' : undefined" :label="STATUS[studio.status].label" />
          </h1>
          <p class="text-sm text-(--ui-text-muted)">
            <template v-if="studio.starts_at">
              الميعاد {{ formatDate(studio.starts_at, true) }} ·
            </template>
            <span class="num">{{ studio.viewers }}</span> بيتفرّجوا دلوقتي · أقصى عدد <span class="num">{{ studio.peak_viewers }}</span> · حضر <span class="num">{{ studio.attended }}</span>
          </p>
        </div>
        <UButton v-if="canHost && studio.status !== 'ended'" color="error" variant="soft" icon="i-lucide-square" label="خلّص الحصة" :loading="ending" @click="end" />
      </div>

      <UAlert v-if="!studio.server_ready" color="warning" variant="subtle" icon="i-lucide-server-off" title="سيرفر البث مش متظبط" description="الحصة اتحفظت، بس البث محتاج LIVE_* في إعدادات السيرفر (docs/DEPLOY.md)." />
      <UAlert v-if="studio.status === 'ended'" :color="studio.recording === 'failed' ? 'error' : 'success'" variant="subtle" icon="i-lucide-clapperboard" :title="RECORDING[studio.recording]" :description="studio.recording_error ?? undefined" />

      <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-3 lg:col-span-2">
          <div class="relative aspect-video overflow-hidden rounded-xl bg-black">
            <video ref="preview" class="h-full w-full object-contain" autoplay muted playsinline />
            <div v-if="!stream" class="absolute inset-0 grid place-items-center text-center text-white/70">
              <div class="space-y-2">
                <UIcon name="i-lucide-video" class="mx-auto size-10" />
                <p class="text-sm">
                  اختار هتبث إيه: الكاميرا، الشاشة، أو الاتنين.
                </p>
              </div>
            </div>
            <UBadge v-if="whip.state.value === 'live'" class="absolute start-3 top-3" color="error" icon="i-lucide-radio" label="على الهوا" />
          </div>

          <div v-if="canHost && studio.status !== 'ended' && studio.publishing" class="flex flex-wrap items-center gap-2">
            <UButton v-for="s in SOURCES" :key="s.value" :color="source === s.value ? 'primary' : 'neutral'" :variant="source === s.value ? 'soft' : 'outline'" :icon="s.icon" :label="s.label" @click="pick(s.value)" />
            <span class="flex-1" />
            <UButton v-if="whip.state.value !== 'live'" icon="i-lucide-radio" :disabled="!stream || !studio.publishing.whip" :loading="whip.state.value === 'connecting'" label="ابدأ البث" @click="goLive" />
            <UButton v-else color="neutral" variant="outline" icon="i-lucide-pause" label="وقّف البث مؤقتاً" @click="whip.stop()" />
          </div>
          <UAlert v-if="whip.error.value" color="error" variant="subtle" :title="whip.error.value" />
          <UAlert v-if="noH264 && canHost && studio.status !== 'ended'" color="warning" variant="subtle" icon="i-lucide-triangle-alert" title="المتصفح ده مش هيوصّل الصورة للطلاب" description="البث بيحتاج H.264، والمتصفح ده ما بيدعموش. ابث من Chrome أو Edge، أو من OBS." />

          <UCard v-if="canHost && studio.publishing && studio.status !== 'ended'" :ui="{ body: 'space-y-2' }">
            <template #header>
              <div class="flex items-center gap-2">
                <UIcon name="i-lucide-cast" class="size-5" />
                <h2 class="flex-1 font-bold">
                  البث من OBS
                </h2>
                <UButton size="xs" color="neutral" variant="ghost" icon="i-lucide-refresh-cw" label="مفتاح جديد" :disabled="studio.status === 'live'" @click="rotate" />
              </div>
            </template>
            <p class="text-sm text-(--ui-text-muted)">
              لجودة أعلى أو كاميرا خارجية: في OBS ← الإعدادات ← البث، اختار «Custom» وحط دول. ما تدّيش المفتاح لحد.
            </p>
            <div v-for="row in obsRows" :key="row.label" class="flex items-center gap-2 rounded-(--ui-radius) bg-(--ui-bg-elevated) p-2 text-sm">
              <span class="w-24 shrink-0 text-(--ui-text-muted)">{{ row.label }}</span>
              <code class="num min-w-0 flex-1 truncate" dir="ltr">{{ row.secret && !showKey ? '••••••••••••' : row.value }}</code>
              <UButton v-if="row.secret" size="xs" color="neutral" variant="ghost" :icon="showKey ? 'i-lucide-eye-off' : 'i-lucide-eye'" :aria-label="showKey ? 'اخفي' : 'اظهر'" @click="showKey = !showKey" />
              <UButton size="xs" color="neutral" variant="ghost" icon="i-lucide-copy" aria-label="نسخ" @click="copy(row.value)" />
            </div>
          </UCard>
        </div>

        <UCard :ui="{ body: 'p-0 sm:p-0' }">
          <UTabs v-model="tab" :items="TABS" class="w-full" :ui="{ list: 'rounded-none' }" />
          <div v-if="tab === 'chat' || tab === 'questions'" class="flex h-[28rem] flex-col">
            <div v-if="tab === 'chat'" class="flex items-center gap-2 border-b border-(--ui-border) p-2 text-sm">
              <span class="text-(--ui-text-muted)">الشات:</span>
              <USelect :model-value="studio.chat_mode" :items="CHAT_MODES" size="xs" class="w-40" @update:model-value="v => setMode(v as ChatMode)" />
            </div>
            <div ref="list" class="flex-1 space-y-2 overflow-y-auto p-3">
              <p v-if="!shown.length" class="pt-10 text-center text-sm text-(--ui-text-muted)">
                {{ tab === 'chat' ? 'لسه مفيش رسايل.' : 'لسه مفيش أسئلة.' }}
              </p>
              <div v-for="m in shown" :key="m.id" class="rounded-(--ui-radius) p-2 text-sm" :class="[m.from_teacher ? 'bg-(--app-primary-soft)' : 'bg-(--ui-bg-elevated)', m.hidden ? 'opacity-50' : '']">
                <div class="flex items-center gap-1">
                  <span class="font-bold">{{ m.author }}</span>
                  <UIcon v-if="m.pinned" name="i-lucide-pin" class="size-3 text-(--ui-primary)" />
                  <UBadge v-if="m.answered" size="sm" color="success" variant="subtle" label="اتجاوب" />
                  <span class="flex-1" />
                  <template v-if="!m.from_teacher">
                    <UButton v-if="m.kind === 'question'" size="xs" color="neutral" variant="ghost" :icon="m.answered ? 'i-lucide-undo-2' : 'i-lucide-check'" :aria-label="m.answered ? 'لسه' : 'اتجاوب'" @click="moderate(m, { answered: !m.answered })" />
                    <UButton size="xs" color="neutral" variant="ghost" :icon="m.pinned ? 'i-lucide-pin-off' : 'i-lucide-pin'" :aria-label="m.pinned ? 'شيل التثبيت' : 'ثبّت للكل'" @click="moderate(m, { pinned: !m.pinned })" />
                    <UButton size="xs" color="neutral" variant="ghost" :icon="m.hidden ? 'i-lucide-eye' : 'i-lucide-eye-off'" :aria-label="m.hidden ? 'اظهر' : 'اخفي'" @click="moderate(m, { hidden: !m.hidden })" />
                  </template>
                </div>
                <p class="whitespace-pre-line">
                  {{ m.body }}
                </p>
              </div>
            </div>
            <form v-if="tab === 'chat' && studio.status !== 'ended'" class="flex gap-2 border-t border-(--ui-border) p-2" @submit.prevent="send">
              <UInput v-model="draft" class="flex-1" placeholder="اكتب للطلاب…" maxlength="1000" />
              <UButton type="submit" icon="i-lucide-send" :disabled="!draft.trim()" aria-label="ابعت" />
            </form>
          </div>

          <div v-else-if="tab === 'poll'" class="space-y-3 p-3">
            <div v-if="poll" class="space-y-2 rounded-(--ui-radius) border border-(--ui-border) p-3">
              <p class="font-bold">
                {{ poll.question }}
              </p>
              <div v-for="(o, i) in poll.options" :key="i" class="space-y-1 text-sm">
                <div class="flex justify-between">
                  <span>{{ o }}</span>
                  <span class="num">{{ poll.results?.[i] ?? 0 }}</span>
                </div>
                <UProgress :model-value="poll.votes ? Math.round((poll.results?.[i] ?? 0) / poll.votes * 100) : 0" size="sm" />
              </div>
              <UButton v-if="poll.open" size="sm" color="neutral" variant="outline" label="اقفل الاستفتاء" @click="closePoll" />
              <UBadge v-else color="neutral" variant="subtle" label="اتقفل" />
            </div>
            <form v-if="studio.status !== 'ended'" class="space-y-2" @submit.prevent="createPoll">
              <UInput v-model="pollForm.question" class="w-full" placeholder="السؤال: فهمتوا قانون أوم؟" />
              <UInput v-for="(_, i) in pollForm.options" :key="i" v-model="pollForm.options[i]" class="w-full" :placeholder="`اختيار ${i + 1}`" />
              <div class="flex gap-2">
                <UButton v-if="pollForm.options.length < 6" size="sm" color="neutral" variant="ghost" icon="i-lucide-plus" label="اختيار" @click="pollForm.options.push('')" />
                <span class="flex-1" />
                <UButton type="submit" size="sm" label="ابعت للطلاب" :disabled="!pollReady" />
              </div>
            </form>
          </div>

          <div v-else class="max-h-[28rem] divide-y divide-(--ui-border) overflow-y-auto">
            <p v-if="!attendance.length" class="p-6 text-center text-sm text-(--ui-text-muted)">
              لسه محدش حضر.
            </p>
            <div v-for="a in attendance" :key="a.learner_id" class="flex items-center gap-2 px-3 py-2 text-sm">
              <span class="flex-1 font-bold">{{ a.name }}</span>
              <span class="num text-(--ui-text-muted)">{{ a.minutes }} دقيقة</span>
            </div>
          </div>
        </UCard>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { ChatMode, LiveMessage, LivePoll, LiveStatus, LiveStudio } from '~/types/manhagy'

definePageMeta({ permission: 'live.moderate' })

type Source = 'camera' | 'screen' | 'both'
const STATUS: Record<LiveStatus, { label: string, color: 'neutral' | 'error' | 'success' }> = {
  idle: { label: 'لسه ما بدأش', color: 'neutral' }, live: { label: 'لايف', color: 'error' }, ended: { label: 'خلصت', color: 'success' },
}
const RECORDING: Record<LiveStudio['recording'], string> = {
  none: 'الحصة خلصت.', collecting: 'بنجمّع التسجيل من سيرفر البث…', processing: 'التسجيل بيتحوّل للجودات… هيتحط في الكورس لوحده.',
  ready: 'التسجيل اتحط في الكورس، والطلاب يقدروا يتفرّجوا عليه.', failed: 'التسجيل ما اكتملش.',
}
const SOURCES = [{ value: 'camera', label: 'الكاميرا', icon: 'i-lucide-video' }, { value: 'screen', label: 'الشاشة', icon: 'i-lucide-monitor' }, { value: 'both', label: 'الشاشة + الكاميرا', icon: 'i-lucide-picture-in-picture-2' }] as const
const CHAT_MODES = [{ value: 'open', label: 'مفتوح للكل' }, { value: 'questions', label: 'أسئلة بس' }, { value: 'off', label: 'مقفول' }]
const TABS = [{ label: 'الشات', value: 'chat' }, { label: 'الأسئلة', value: 'questions' }, { label: 'استفتاء', value: 'poll' }, { label: 'الحضور', value: 'attendance' }]

const lessonId = String(useRoute().params.lesson)
const api = useApi()
const toast = useToast()
const store = useSessionStore()
const canHost = computed(() => store.can('live.host'))
const whip = useWhip()

const studio = ref<LiveStudio | null>(null)
const messages = ref<LiveMessage[]>([])
const poll = ref<LivePoll | null>(null)
const attendance = ref<{ learner_id: string, name: string, minutes: number }[]>([])
const tab = ref('chat')
const draft = ref('')
const ending = ref(false)
const showKey = ref(false)
const source = ref<Source | null>(null)
const stream = ref<MediaStream | null>(null)
const preview = ref<HTMLVideoElement | null>(null)
const list = ref<HTMLElement | null>(null)
const pollForm = reactive({ question: '', options: ['', ''] })
// Students' players need H.264: a browser that can't send it (some Chromium / Firefox builds) only sends VP8.
const noH264 = ref(false)
onMounted(() => {
  noH264.value = !(RTCRtpSender.getCapabilities?.('video')?.codecs ?? []).some(c => c.mimeType.toLowerCase() === 'video/h264')
})
const pollReady = computed(() => pollForm.question.trim().length > 1 && pollForm.options.filter(o => o.trim()).length >= 2)
let lastSeq = 0
let raw: MediaStream[] = []
let compose: number | undefined

const shown = computed(() => messages.value.filter(m => (tab.value === 'questions' ? m.kind === 'question' : m.kind === 'chat' || m.from_teacher)))
const obsRows = computed(() => studio.value?.publishing
  ? [{ label: 'Server', value: studio.value.publishing.rtmp_server ?? '—', secret: false }, { label: 'Stream key', value: studio.value.publishing.rtmp_key, secret: true }]
  : [])

async function load() {
  studio.value = (await api<{ data: LiveStudio }>(`/live/${lessonId}`)).data
}
async function loadFeed() {
  const res = (await api<{ data: { messages: LiveMessage[], poll: LivePoll | null, viewers: number, status: LiveStatus } }>(`/live/${lessonId}/feed`, { query: { after: lastSeq } })).data
  if (res.messages.length) {
    messages.value = [...messages.value, ...res.messages]
    lastSeq = res.messages.at(-1)!.seq
    nextTick(() => list.value?.scrollTo({ top: list.value.scrollHeight, behavior: 'smooth' }))
  }
  poll.value = res.poll
  if (studio.value) {
    studio.value.viewers = res.viewers
    studio.value.status = res.status
  }
}

let feedTimer: ReturnType<typeof setInterval> | undefined
let stateTimer: ReturnType<typeof setInterval> | undefined
onMounted(async () => {
  await load()
  await loadFeed()
  feedTimer = setInterval(() => !document.hidden && loadFeed().catch(() => {}), 3000)
  stateTimer = setInterval(() => !document.hidden && load().catch(() => {}), 15000)
})
watch(tab, t => t === 'attendance' && api<{ data: typeof attendance.value }>(`/live/${lessonId}/attendance`).then(r => (attendance.value = r.data)))
onBeforeUnmount(() => {
  clearInterval(feedTimer)
  clearInterval(stateTimer)
  whip.stop()
  release()
})

function release() {
  cancelAnimationFrame(compose ?? 0)
  for (const s of raw) s.getTracks().forEach(t => t.stop())
  raw = []
}

/** The screen with the teacher's camera in a corner, drawn on a canvas. */
function composite(screen: MediaStream, camera: MediaStream): MediaStream {
  const canvas = document.createElement('canvas')
  canvas.width = 1280
  canvas.height = 720
  const ctx = canvas.getContext('2d')!
  const sv = Object.assign(document.createElement('video'), { srcObject: screen, muted: true, playsInline: true })
  const cv = Object.assign(document.createElement('video'), { srcObject: camera, muted: true, playsInline: true })
  sv.play()
  cv.play()
  const draw = () => {
    ctx.fillStyle = '#000'
    ctx.fillRect(0, 0, 1280, 720)
    if (sv.videoWidth) {
      const scale = Math.min(1280 / sv.videoWidth, 720 / sv.videoHeight)
      ctx.drawImage(sv, (1280 - sv.videoWidth * scale) / 2, (720 - sv.videoHeight * scale) / 2, sv.videoWidth * scale, sv.videoHeight * scale)
    }
    if (cv.videoWidth) {
      ctx.save()
      ctx.beginPath()
      ctx.arc(1280 - 130, 720 - 130, 110, 0, Math.PI * 2)
      ctx.clip()
      const side = Math.min(cv.videoWidth, cv.videoHeight)
      ctx.drawImage(cv, (cv.videoWidth - side) / 2, (cv.videoHeight - side) / 2, side, side, 1280 - 240, 720 - 240, 220, 220)
      ctx.restore()
    }
    compose = requestAnimationFrame(draw)
  }
  draw()
  return canvas.captureStream(25)
}

async function pick(value: Source) {
  try {
    const mic = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true } })
    const camera = value !== 'screen' ? await navigator.mediaDevices.getUserMedia({ video: { width: 1280, height: 720 } }) : null
    const screen = value !== 'camera' ? await navigator.mediaDevices.getDisplayMedia({ video: { frameRate: 25 } }) : null
    const old = raw
    raw = [mic, ...(camera ? [camera] : []), ...(screen ? [screen] : [])]
    cancelAnimationFrame(compose ?? 0)
    const video = value === 'both' ? composite(screen!, camera!) : (camera ?? screen)!
    const next = new MediaStream([video.getVideoTracks()[0]!, mic.getAudioTracks()[0]!])
    if (whip.state.value === 'live') await whip.replaceVideo(next.getVideoTracks()[0]!)
    for (const s of old) s.getTracks().forEach(t => t.stop())
    stream.value = next
    source.value = value
    if (preview.value) preview.value.srcObject = next
  }
  catch {
    toast.add({ color: 'error', title: 'المتصفح ما سمحش بالكاميرا أو الشاشة. اسمح بيها من شريط العنوان.' })
  }
}

async function goLive() {
  if (!studio.value?.publishing?.whip || !stream.value) return
  await whip.start(studio.value.publishing.whip, stream.value)
  if (whip.state.value !== 'failed') setTimeout(() => load().catch(() => {}), 2000)
}

async function end() {
  if (!confirm('تخلّص الحصة؟ البث هيقف والطلاب هيخرجوا، والتسجيل هيتحط في الكورس.')) return
  ending.value = true
  try {
    await whip.stop()
    release()
    stream.value = null
    studio.value = (await api<{ data: LiveStudio }>(`/live/${lessonId}/end`, { method: 'POST' })).data
  }
  finally {
    ending.value = false
  }
}

async function rotate() {
  if (!confirm('مفتاح جديد؟ OBS هيحتاج المفتاح الجديد.')) return
  studio.value = (await api<{ data: LiveStudio }>(`/live/${lessonId}/key`, { method: 'POST' })).data
}

async function setMode(mode: ChatMode) {
  studio.value = (await api<{ data: LiveStudio }>(`/live/${lessonId}`, { method: 'PATCH', body: { chat_mode: mode } })).data
}

async function send() {
  const body = draft.value.trim()
  if (!body) return
  draft.value = ''
  await api(`/live/${lessonId}/messages`, { method: 'POST', body: { body } })
  await loadFeed()
}

async function moderate(m: LiveMessage, change: Partial<Pick<LiveMessage, 'pinned' | 'answered' | 'hidden'>>) {
  const updated = (await api<{ data: LiveMessage }>(`/live/messages/${m.id}`, { method: 'PATCH', body: change })).data
  Object.assign(m, updated)
}

async function createPoll() {
  poll.value = (await api<{ data: LivePoll }>(`/live/${lessonId}/polls`, { method: 'POST', body: { question: pollForm.question.trim(), options: pollForm.options.map(o => o.trim()).filter(Boolean) } })).data
  pollForm.question = ''
  pollForm.options = ['', '']
}

async function closePoll() {
  if (poll.value) poll.value = (await api<{ data: LivePoll }>(`/live/polls/${poll.value.id}/close`, { method: 'POST' })).data
}

async function copy(text: string) {
  await navigator.clipboard.writeText(text).catch(() => {})
  toast.add({ color: 'success', title: 'اتنسخ' })
}
</script>
