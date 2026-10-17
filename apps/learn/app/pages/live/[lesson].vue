<template>
  <div class="space-y-4">
    <USkeleton v-if="!room && !error" class="aspect-video w-full rounded-xl" />
    <UAlert v-else-if="error" color="error" variant="subtle" :title="error" :actions="[{ label: 'رجوع', onClick: () => router.back() }]" />
    <template v-else-if="room">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <h1 class="text-xl font-bold">
          {{ room.title }}
        </h1>
        <span v-if="viewers" class="num text-sm text-(--ui-text-muted)">{{ viewers }} بيتفرّجوا</span>
      </div>

      <!-- Waiting room -->
      <div v-if="room.status === 'idle'" class="app-card space-y-3 p-8 text-center">
        <UIcon name="i-lucide-radio" class="mx-auto size-10 text-primary" />
        <p class="text-lg font-bold">
          الحصة لسه ما بدأتش
        </p>
        <p v-if="countdown" class="num text-3xl font-extrabold text-primary">
          {{ countdown }}
        </p>
        <p class="text-sm text-(--ui-text-muted)">
          <template v-if="room.starts_at">
            ميعادها {{ formatDate(room.starts_at, true) }}.
          </template>
          سيب الصفحة مفتوحة، هتبدأ لوحدها أول ما المدرس يبدأ.
        </p>
      </div>

      <!-- Ended -->
      <div v-else-if="room.status === 'ended'" class="app-card space-y-3 p-8 text-center">
        <UIcon name="i-lucide-circle-check" class="mx-auto size-10 text-(--ui-success)" />
        <p class="text-lg font-bold">
          الحصة خلصت
        </p>
        <UButton v-if="room.replay_ready" :to="`/lessons/${lessonId}`" icon="i-lucide-play" label="اتفرّج على التسجيل" />
        <p v-else class="text-sm text-(--ui-text-muted)">
          التسجيل هيتحط هنا أول ما يجهز.
        </p>
      </div>

      <!-- On air -->
      <template v-else>
        <div v-if="left" class="app-card space-y-3 p-8 text-center">
          <UIcon name="i-lucide-circle-pause" class="mx-auto size-10" />
          <p class="font-bold">
            {{ left }}
          </p>
          <UButton label="ادخل تاني هنا" @click="join" />
        </div>
        <LivePlayer v-else-if="joined?.stream_url" ref="player" :key="joined.attendance_id" :src="joined.stream_url" :watermark="joined.watermark?.text ?? null" @broken="left = 'البث وقف. لو فتحته من جهاز تاني، اتقفل هنا.'" />
      </template>

      <!-- Poll -->
      <div v-if="poll && room.status !== 'idle'" class="app-card space-y-2 p-4">
        <p class="flex items-center gap-2 font-bold">
          <UIcon name="i-lucide-chart-bar" class="size-4 text-primary" />{{ poll.question }}
        </p>
        <template v-if="poll.results === null">
          <UButton v-for="(o, i) in poll.options" :key="i" block color="neutral" variant="outline" :label="o" @click="vote(i)" />
        </template>
        <div v-for="(o, i) in poll.options" v-else :key="i" class="space-y-1 text-sm">
          <div class="flex justify-between">
            <span :class="poll.my_vote === i ? 'font-bold text-primary' : ''">{{ o }}<template v-if="poll.my_vote === i"> ✓</template></span>
            <span class="num">{{ poll.votes ? Math.round((poll.results[i] ?? 0) / poll.votes * 100) : 0 }}%</span>
          </div>
          <UProgress :model-value="poll.votes ? Math.round((poll.results[i] ?? 0) / poll.votes * 100) : 0" size="sm" />
        </div>
      </div>

      <!-- Chat and questions -->
      <div v-if="room.status === 'live'" class="app-card flex h-96 flex-col">
        <div ref="list" class="flex-1 space-y-2 overflow-y-auto p-3">
          <p v-if="!messages.length" class="pt-10 text-center text-sm text-(--ui-text-muted)">
            {{ room.chat_mode === 'off' ? 'المدرس قفل الشات.' : 'اسأل المدرس أو اكتب في الشات.' }}
          </p>
          <div v-for="m in messages" :key="m.id" class="rounded-(--ui-radius) p-2 text-sm" :class="m.from_teacher ? 'bg-primary/10' : m.kind === 'question' ? 'bg-amber-500/10' : 'bg-(--ui-bg-elevated)'">
            <p class="flex items-center gap-1 text-xs">
              <span class="font-bold" :class="m.from_teacher ? 'text-primary' : ''">{{ m.from_teacher ? `${m.author} (المدرس)` : m.mine ? 'انت' : m.author }}</span>
              <UBadge v-if="m.kind === 'question'" size="sm" color="warning" variant="subtle" label="سؤال" />
              <UBadge v-if="m.answered" size="sm" color="success" variant="subtle" label="اتجاوب" />
              <UIcon v-if="m.pinned" name="i-lucide-pin" class="size-3 text-primary" />
            </p>
            <p class="whitespace-pre-line">
              {{ m.body }}
            </p>
          </div>
        </div>
        <form v-if="room.chat_mode !== 'off'" class="flex gap-2 border-t border-(--ui-border) p-2" @submit.prevent="send">
          <UButton v-if="room.chat_mode === 'open'" :color="asking ? 'warning' : 'neutral'" variant="soft" :label="asking ? 'سؤال' : 'رسالة'" @click="asking = !asking" />
          <UInput v-model="draft" class="flex-1" :placeholder="asking || room.chat_mode === 'questions' ? 'اسأل المدرس…' : 'اكتب رسالة…'" maxlength="500" />
          <UButton type="submit" icon="i-lucide-send" :disabled="!draft.trim()" aria-label="ابعت" />
        </form>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
interface Room { lesson_id: string, title: string, starts_at: string | null, status: 'idle' | 'live' | 'ended', chat_mode: 'open' | 'questions' | 'off', replay_ready: boolean }
interface Joined extends Room { attendance_id?: string, stream_url?: string, beat_seconds?: number, watermark?: { text: string } }
interface Message { id: string, seq: number, kind: 'chat' | 'question', body: string, author: string, from_teacher: boolean, mine: boolean, pinned: boolean, answered: boolean }
interface Poll { id: string, question: string, options: string[], open: boolean, my_vote: number | null, results: number[] | null, votes: number }

definePageMeta({ auth: true })

const lessonId = String(useRoute().params.lesson)
const router = useRouter()
const api = useLearnApi()
const toast = useToast()
const config = useRuntimeConfig().public
const { token } = useLearner()

const room = ref<Room | null>(null)
const joined = ref<Joined | null>(null)
const error = ref<string | null>(null)
const left = ref<string | null>(null)
const messages = ref<Message[]>([])
const poll = ref<Poll | null>(null)
const viewers = ref(0)
const draft = ref('')
const asking = ref(false)
const list = ref<HTMLElement | null>(null)
const player = ref<{ playing: () => boolean } | null>(null)
const now = ref(Date.now())
let lastSeq = 0

const countdown = computed(() => {
  if (!room.value?.starts_at) return ''
  const s = Math.max(0, Math.floor((new Date(room.value.starts_at).getTime() - now.value) / 1000))
  if (!s) return ''
  const h = Math.floor(s / 3600)
  const m = Math.floor((s % 3600) / 60)
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${h ? `${h}:` : ''}${pad(m)}:${pad(s % 60)}`
})

async function join() {
  error.value = null
  left.value = null
  try {
    const res = (await api<{ data: Joined }>(`/learner/live/${lessonId}/join`, { method: 'POST' })).data
    room.value = res
    joined.value = res.stream_url ? res : null
  }
  catch (e) {
    error.value = apiMessage(e)
  }
}

async function feed() {
  const res = (await api<{ data: Room & { messages: Message[], poll: Poll | null } }>(`/learner/live/${lessonId}/feed`, { query: { after: lastSeq } })).data
  const before = room.value?.status
  room.value = { ...room.value!, ...res }
  if (res.messages.length) {
    messages.value = [...messages.value, ...res.messages.filter(m => !messages.value.some(x => x.id === m.id))].sort((a, b) => a.seq - b.seq)
    lastSeq = Math.max(lastSeq, ...res.messages.map(m => m.seq))
    nextTick(() => list.value?.scrollTo({ top: list.value.scrollHeight, behavior: 'smooth' }))
  }
  poll.value = res.poll
  // The teacher just went live: in.
  if (before === 'idle' && res.status === 'live') await join()
}

async function beat() {
  if (!joined.value?.attendance_id || left.value) return
  try {
    const res = (await api<{ data: { viewers: number } }>(`/learner/live/attendances/${joined.value.attendance_id}/beat`, { method: 'POST', body: { playing: player.value?.playing() ?? false } })).data
    viewers.value = res.viewers
  }
  catch (e) {
    if (apiCode(e) === 'live_left') {
      const reason = (e as { data?: { reason?: string } }).data?.reason
      if (reason === 'session_ended') room.value = { ...room.value!, status: 'ended' }
      else left.value = apiMessage(e)
    }
  }
}

async function send() {
  const body = draft.value.trim()
  if (!body || !room.value) return
  const kind = room.value.chat_mode === 'questions' || asking.value ? 'question' : 'chat'
  // Cleared at once, so the next message can be typed while this one is on its way.
  draft.value = ''
  asking.value = false
  try {
    const m = (await api<{ data: Message }>(`/learner/live/${lessonId}/messages`, { method: 'POST', body: { kind, body } })).data
    // Shown now; the feed brings it again (kept once by id) without skipping earlier messages.
    if (!messages.value.some(x => x.id === m.id)) messages.value.push(m)
    nextTick(() => list.value?.scrollTo({ top: list.value.scrollHeight, behavior: 'smooth' }))
  }
  catch (e) {
    draft.value ||= body
    toast.add({ color: 'error', title: apiMessage(e) })
  }
}

async function vote(option: number) {
  if (!poll.value) return
  poll.value = (await api<{ data: Poll }>(`/learner/live/polls/${poll.value.id}/vote`, { method: 'POST', body: { option } })).data
}

let feedTimer: ReturnType<typeof setInterval> | undefined
let beatTimer: ReturnType<typeof setInterval> | undefined
let clock: ReturnType<typeof setInterval> | undefined
onMounted(async () => {
  await join()
  if (error.value) return
  await feed().catch(() => {})
  feedTimer = setInterval(() => !document.hidden && feed().catch(() => {}), 4000)
  beatTimer = setInterval(beat, (joined.value?.beat_seconds ?? 30) * 1000)
  clock = setInterval(() => (now.value = Date.now()), 1000)
})

function leave() {
  if (!joined.value?.attendance_id) return
  fetch(`${config.apiBase}/learner/live/attendances/${joined.value.attendance_id}/leave`, { method: 'POST', keepalive: true, headers: { Accept: 'application/json', Authorization: `Bearer ${token.value}` } }).catch(() => {})
}
onBeforeUnmount(() => {
  clearInterval(feedTimer)
  clearInterval(beatTimer)
  clearInterval(clock)
  leave()
})
</script>
