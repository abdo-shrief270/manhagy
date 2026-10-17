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

      <UAlert v-if="!studio.server_ready" color="warning" variant="subtle" icon="i-lucide-server-off" title="سيرفر البث مش متظبط" description="الحصة اتحفظت، بس البث محتاج LIVEKIT_* في إعدادات السيرفر (docs/DEPLOY.md)." />
      <UAlert v-if="studio.status === 'ended' && studio.recording !== 'none'" :color="studio.recording === 'failed' ? 'error' : 'success'" variant="subtle" icon="i-lucide-clapperboard" :title="RECORDING[studio.recording]" :description="studio.recording_error ?? undefined" />

      <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-3 lg:col-span-2">
          <LiveStage :tracks="lk.tracks.value" :speaking="lk.speaking.value">
            <template #empty>
              <div class="space-y-2">
                <UIcon :name="lk.state.value === 'connected' ? 'i-lucide-video-off' : 'i-lucide-video'" class="mx-auto size-10" />
                <p class="text-sm">
                  <template v-if="lk.state.value === 'connected'">
                    {{ canHost ? 'افتح الكاميرا أو اعرض الشاشة.' : 'المدرس لسه ما فتحش الكاميرا.' }}
                  </template>
                  <template v-else-if="studio.status === 'ended'">
                    الحصة خلصت.
                  </template>
                  <template v-else>
                    {{ canHost ? 'ادخل الاستوديو، والطلاب هيشوفوك ويسمعوك في نفس اللحظة.' : 'ادخل تتفرّج على البث.' }}
                  </template>
                </p>
              </div>
            </template>
            <template #overlay>
              <UBadge v-if="lk.state.value === 'connected' && canHost" class="absolute start-3 top-3" color="error" icon="i-lucide-radio" label="على الهوا" />
              <UBadge v-else-if="lk.state.value === 'reconnecting'" class="absolute start-3 top-3" color="warning" icon="i-lucide-wifi-off" label="بنرجّع الاتصال…" />
              <UButton v-if="!lk.canPlayAudio.value" class="absolute inset-x-0 bottom-3 mx-auto w-fit" icon="i-lucide-volume-2" label="شغّل الصوت" @click="lk.startAudio()" />
            </template>
          </LiveStage>

          <div v-if="studio.status !== 'ended' && studio.server_ready" class="flex flex-wrap items-center gap-2">
            <UButton v-if="lk.state.value === 'idle' || lk.state.value === 'disconnected'" icon="i-lucide-radio" :loading="joining" :label="canHost ? (studio.status === 'live' ? 'ارجع للاستوديو' : 'ادخل الاستوديو وابدأ') : 'اتفرّج على البث'" @click="enter" />
            <template v-else-if="canHost">
              <UButton :color="lk.mic.value ? 'primary' : 'neutral'" :variant="lk.mic.value ? 'soft' : 'outline'" :icon="lk.mic.value ? 'i-lucide-mic' : 'i-lucide-mic-off'" :label="lk.mic.value ? 'المايك شغال' : 'المايك مقفول'" @click="toggle('mic', !lk.mic.value)" />
              <UButton :color="lk.camera.value ? 'primary' : 'neutral'" :variant="lk.camera.value ? 'soft' : 'outline'" :icon="lk.camera.value ? 'i-lucide-video' : 'i-lucide-video-off'" :label="lk.camera.value ? 'الكاميرا شغالة' : 'الكاميرا مقفولة'" @click="toggle('camera', !lk.camera.value)" />
              <UButton :color="lk.screen.value ? 'primary' : 'neutral'" :variant="lk.screen.value ? 'soft' : 'outline'" :icon="lk.screen.value ? 'i-lucide-monitor-off' : 'i-lucide-monitor-up'" :label="lk.screen.value ? 'وقّف عرض الشاشة' : 'اعرض الشاشة'" @click="toggle('screen', !lk.screen.value)" />
              <span class="flex-1" />
              <span class="text-xs text-(--ui-text-muted)">بيتسجّل وهيتحط في الكورس</span>
            </template>
          </div>
          <UAlert v-if="studio.status === 'live' && studio.recording === 'failed'" color="warning" variant="subtle" icon="i-lucide-circle-off" title="الحصة مش بتتسجل" :description="studio.recording_error ?? undefined" />
          <UAlert v-if="lk.reason.value === 'replaced'" color="warning" variant="subtle" icon="i-lucide-monitor-smartphone" title="دخلت الاستوديو من جهاز أو تاب تاني، فاتقفل هنا." />
          <UAlert v-else-if="lk.reason.value === 'lost' && studio.status === 'live'" color="error" variant="subtle" icon="i-lucide-wifi-off" title="الاتصال بسيرفر البث وقع." description="ادخل تاني؛ الطلاب لسه في الحصة." />

          <UCard v-if="canHost && studio.obs_available && studio.status !== 'ended'" :ui="{ body: 'space-y-2' }">
            <template #header>
              <div class="flex items-center gap-2">
                <UIcon name="i-lucide-cast" class="size-5" />
                <h2 class="flex-1 font-bold">
                  البث من OBS
                </h2>
                <UButton size="xs" color="neutral" variant="ghost" icon="i-lucide-key-round" :label="studio.obs ? 'مفتاح جديد' : 'اعمل مفتاح'" :loading="makingObs" @click="obs" />
              </div>
            </template>
            <p class="text-sm text-(--ui-text-muted)">
              لكاميرا خارجية أو مشهد متجهّز: في OBS ← الإعدادات ← البث، اختار «Custom» وحط دول. OBS بيتأخر ثانيتين تقريباً عن البث من المتصفح. ما تدّيش المفتاح لحد.
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

          <div v-else-if="tab === 'hands'" class="max-h-[28rem] divide-y divide-(--ui-border) overflow-y-auto">
            <p v-if="!hands.length" class="p-6 text-center text-sm text-(--ui-text-muted)">
              محدش رافع إيده. الطالب اللي عايز يتكلم بيرفع إيده، وانت تدّيله المايك (والكاميرا لو حبيت).
            </p>
            <div v-for="h in hands" :key="h.learner_id" class="space-y-2 px-3 py-2 text-sm">
              <div class="flex items-center gap-2">
                <UIcon :name="h.status === 'speaking' ? 'i-lucide-mic' : 'i-lucide-hand'" class="size-4" :class="h.status === 'speaking' ? 'text-(--ui-success)' : 'text-(--ui-warning)'" />
                <span class="flex-1 font-bold">{{ h.name }}</span>
                <span v-if="h.status === 'speaking'" class="text-xs text-(--ui-success)">بيتكلم{{ h.camera ? ' بالكاميرا' : '' }}</span>
              </div>
              <div v-if="canHost" class="flex flex-wrap gap-1">
                <template v-if="h.status === 'raised'">
                  <UButton size="xs" icon="i-lucide-mic" label="ادّيله المايك" @click="floor(h, true, false)" />
                  <UButton size="xs" color="neutral" variant="outline" icon="i-lucide-video" label="مايك + كاميرا" @click="floor(h, true, true)" />
                  <UButton size="xs" color="neutral" variant="ghost" label="نزّل إيده" @click="floor(h, false, false)" />
                </template>
                <template v-else>
                  <UButton v-if="!h.camera" size="xs" color="neutral" variant="outline" icon="i-lucide-video" label="افتحله الكاميرا" @click="floor(h, true, true)" />
                  <UButton size="xs" color="error" variant="soft" icon="i-lucide-mic-off" label="خلّص كلامه" @click="floor(h, false, false)" />
                </template>
              </div>
            </div>
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
import type { ChatMode, LiveAccess, LiveHand, LiveMessage, LivePoll, LiveStatus, LiveStudio } from '~/types/manhagy'

definePageMeta({ permission: 'live.moderate' })

const STATUS: Record<LiveStatus, { label: string, color: 'neutral' | 'error' | 'success' }> = {
  idle: { label: 'لسه ما بدأش', color: 'neutral' }, live: { label: 'لايف', color: 'error' }, ended: { label: 'خلصت', color: 'success' },
}
const RECORDING: Record<LiveStudio['recording'], string> = {
  none: 'الحصة خلصت.', recording: 'الحصة خلصت.', collecting: 'بنجمّع التسجيل من سيرفر البث…', processing: 'التسجيل بيتحوّل للجودات… هيتحط في الكورس لوحده.',
  ready: 'التسجيل اتحط في الكورس، والطلاب يقدروا يتفرّجوا عليه.', failed: 'التسجيل ما اكتملش.',
}
const CHAT_MODES = [{ value: 'open', label: 'مفتوح للكل' }, { value: 'questions', label: 'أسئلة بس' }, { value: 'off', label: 'مقفول' }]

const lessonId = String(useRoute().params.lesson)
const api = useApi()
const toast = useToast()
const store = useSessionStore()
const canHost = computed(() => store.can('live.host'))

const studio = ref<LiveStudio | null>(null)
const messages = ref<LiveMessage[]>([])
const poll = ref<LivePoll | null>(null)
const hands = ref<LiveHand[]>([])
const attendance = ref<{ learner_id: string, name: string, minutes: number }[]>([])
const tab = ref('chat')
const draft = ref('')
const ending = ref(false)
const joining = ref(false)
const makingObs = ref(false)
const showKey = ref(false)
const list = ref<HTMLElement | null>(null)
const pollForm = reactive({ question: '', options: ['', ''] })
const pollReady = computed(() => pollForm.question.trim().length > 1 && pollForm.options.filter(o => o.trim()).length >= 2)
const raised = computed(() => hands.value.filter(h => h.status === 'raised').length)
const TABS = computed(() => [
  { label: 'الشات', value: 'chat' }, { label: 'الأسئلة', value: 'questions' },
  { label: raised.value ? `الإيدين (${raised.value})` : 'الإيدين', value: 'hands' },
  { label: 'استفتاء', value: 'poll' }, { label: 'الحضور', value: 'attendance' },
])
let lastSeq = 0

// The server nudges the room when something changes (a message, a hand, a vote): look again at once.
let pending: ReturnType<typeof setTimeout> | undefined
const lk = useLiveRoom((type) => {
  if (type === 'ended') return load()
  clearTimeout(pending)
  pending = setTimeout(() => loadFeed().catch(() => {}), 150)
})

const shown = computed(() => messages.value.filter(m => (tab.value === 'questions' ? m.kind === 'question' : m.kind === 'chat' || m.from_teacher)))
const obsRows = computed(() => studio.value?.obs
  ? [{ label: 'Server', value: studio.value.obs.server, secret: false }, { label: 'Stream key', value: studio.value.obs.key, secret: true }]
  : [])

async function load() {
  studio.value = (await api<{ data: LiveStudio }>(`/live/${lessonId}`)).data
  hands.value = studio.value.hands
}
async function loadFeed() {
  const res = (await api<{ data: { messages: LiveMessage[], poll: LivePoll | null, viewers: number, status: LiveStatus, hands: LiveHand[] } }>(`/live/${lessonId}/feed`, { query: { after: lastSeq } })).data
  if (res.messages.length) {
    messages.value = [...messages.value, ...res.messages.filter(m => !messages.value.some(x => x.id === m.id))]
    lastSeq = res.messages.at(-1)!.seq
    nextTick(() => list.value?.scrollTo({ top: list.value.scrollHeight, behavior: 'smooth' }))
  }
  poll.value = res.poll
  hands.value = res.hands
  if (studio.value) {
    studio.value.viewers = res.viewers
    studio.value.status = res.status
  }
}

/** Into the LiveKit room: the teacher goes on air (camera + microphone), a moderator watches. */
async function enter() {
  joining.value = true
  try {
    const res = (await api<{ data: LiveStudio & { livekit: LiveAccess } }>(`/live/${lessonId}/join`, { method: 'POST' })).data
    studio.value = res
    await lk.connect(res.livekit.url, res.livekit.token)
    if (canHost.value) {
      await lk.toggle('mic', true).catch(() => toast.add({ color: 'error', title: 'المتصفح ما سمحش بالمايك. اسمح بيه من شريط العنوان.' }))
      await lk.toggle('camera', true).catch(() => toast.add({ color: 'warning', title: 'الكاميرا مش متاحة؛ تقدر تعرض الشاشة.' }))
    }
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) || 'ما قدرناش نوصل لسيرفر البث.' })
  }
  finally {
    joining.value = false
  }
}

async function toggle(kind: 'mic' | 'camera' | 'screen', on: boolean) {
  try {
    await lk.toggle(kind, on)
  }
  catch {
    // Cancelling the screen picker lands here too.
    if (kind !== 'screen') toast.add({ color: 'error', title: 'المتصفح ما سمحش بالكاميرا أو المايك. اسمح بيهم من شريط العنوان.' })
  }
}

async function floor(h: LiveHand, allow: boolean, camera: boolean) {
  try {
    await api(`/live/${lessonId}/floor`, { method: 'POST', body: { learner_id: h.learner_id, allow, camera } })
    await loadFeed()
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
}

let feedTimer: ReturnType<typeof setInterval> | undefined
let stateTimer: ReturnType<typeof setInterval> | undefined
onMounted(async () => {
  await load()
  await loadFeed()
  // A fallback for nudges: often while not in the room, rarely while in it.
  let tick = 0
  feedTimer = setInterval(() => !document.hidden && (lk.state.value !== 'connected' || ++tick % 4 === 0) && loadFeed().catch(() => {}), 3000)
  stateTimer = setInterval(() => !document.hidden && load().catch(() => {}), 15000)
})
watch(tab, t => t === 'attendance' && api<{ data: typeof attendance.value }>(`/live/${lessonId}/attendance`).then(r => (attendance.value = r.data)))
watch(() => lk.reason.value, r => r === 'ended' && load())
onBeforeUnmount(() => {
  clearInterval(feedTimer)
  clearInterval(stateTimer)
  clearTimeout(pending)
})

async function end() {
  if (!confirm('تخلّص الحصة؟ البث هيقف والطلاب هيخرجوا، والتسجيل هيتحط في الكورس.')) return
  ending.value = true
  try {
    studio.value = (await api<{ data: LiveStudio }>(`/live/${lessonId}/end`, { method: 'POST' })).data
    lk.disconnect()
  }
  finally {
    ending.value = false
  }
}

async function obs() {
  if (studio.value?.obs && !confirm('مفتاح جديد؟ OBS هيحتاج المفتاح الجديد.')) return
  makingObs.value = true
  try {
    studio.value = (await api<{ data: LiveStudio }>(`/live/${lessonId}/obs`, { method: 'POST' })).data
    showKey.value = false
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    makingObs.value = false
  }
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
