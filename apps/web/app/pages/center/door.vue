<template>
  <div class="max-w-5xl space-y-4">
    <PageHeader title="الباب" description="امسح كارنيه الطالب: الحضور بيتسجّل، ولو عليه فلوس تاخدها. بيشتغل حتى لو النت قاطع.">
      <UBadge v-if="door.queue.value.length" color="warning" variant="subtle" icon="i-lucide-cloud-upload" :label="`${door.queue.value.length} مستني يتبعت`" />
      <UBadge v-else color="success" variant="subtle" icon="i-lucide-cloud" label="كله اتبعت" />
    </PageHeader>

    <!-- Which session -->
    <UCard v-if="!roster" :ui="{ body: 'space-y-3' }">
      <p class="font-bold">
        حصص النهارده
      </p>
      <p v-if="!today?.length" class="text-sm text-(--ui-text-muted)">
        مفيش حصص النهارده. افتح حصة لمجموعة من صفحة المجموعات.
      </p>
      <button v-for="s in today" :key="s.id" type="button" class="flex w-full items-center justify-between rounded-(--ui-radius) border border-(--ui-border) p-3 text-start transition hover:border-primary" @click="openSession(s.id)">
        <span>
          <span class="block font-bold">{{ s.group }}</span>
          <span class="text-xs text-(--ui-text-muted)"><span class="num">{{ formatDate(s.starts_at, true) }}</span><template v-if="s.room"> · {{ s.room }}</template></span>
        </span>
        <span class="num text-sm">{{ s.present }} / {{ s.members }}</span>
      </button>
    </UCard>

    <template v-else>
      <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
          <p class="text-lg font-bold">
            {{ roster.group }}
          </p>
          <p class="text-sm text-(--ui-text-muted)">
            <span class="num">{{ formatDate(roster.starts_at, true) }}</span><template v-if="roster.room"> · {{ roster.room }}</template> · حضر <span class="num font-bold">{{ presentCount }}</span> من <span class="num">{{ members.length }}</span>
          </p>
        </div>
        <UButton color="neutral" variant="ghost" icon="i-lucide-arrow-right" label="حصة تانية" @click="leave" />
      </div>

      <div class="grid gap-4 lg:grid-cols-2">
        <div class="space-y-3">
          <QrScanner @read="onRead" />
          <div v-if="flash" class="rounded-xl p-4 text-center" :class="flash.ok ? 'bg-(--ui-success)/15' : 'bg-(--ui-error)/15'">
            <p class="text-xl font-extrabold">
              {{ flash.title }}
            </p>
            <p v-if="flash.detail" class="text-sm">
              {{ flash.detail }}
            </p>
            <div v-if="flash.owes && flash.learner" class="mt-3 flex flex-wrap justify-center gap-2">
              <UButton color="success" :label="`دفع الحصة ${formatMoney(roster.session_fee)}`" @click="pay(flash.learner, 'session', roster.session_fee)" />
              <UButton v-if="roster.monthly_fee" color="success" variant="soft" :label="`دفع الشهر ${formatMoney(roster.monthly_fee)}`" @click="pay(flash.learner, 'month', roster.monthly_fee)" />
              <UButton color="neutral" variant="ghost" label="بعدين" @click="flash = null" />
            </div>
          </div>
        </div>

        <UCard :ui="{ body: 'p-0 sm:p-0' }">
          <template #header>
            <UInput v-model="search" icon="i-lucide-search" class="w-full" placeholder="مش معاه الكارنيه؟ دوّر بالاسم" />
          </template>
          <div class="max-h-[60vh] overflow-y-auto">
            <div v-for="s in shown" :key="s.learner_id" class="flex items-center gap-3 border-b border-(--ui-border) px-4 py-2.5 text-sm last:border-0">
              <UIcon :name="s.present ? 'i-lucide-circle-check' : 'i-lucide-circle-dashed'" class="size-5" :class="s.present ? 'text-(--ui-success)' : 'text-(--ui-text-muted)'" />
              <span class="flex-1 font-bold">{{ s.name }}<span v-if="s.late" class="text-xs font-normal text-(--ui-warning)"> · متأخر</span></span>
              <span v-if="s.owes" class="num text-xs text-(--ui-error)">عليه {{ formatMoney(s.owes) }}</span>
              <UButton v-if="!s.present" size="xs" variant="soft" label="حاضر" @click="mark(s)" />
              <UButton v-else-if="s.owes" size="xs" color="success" variant="soft" label="دفع" @click="flash = { ok: true, title: s.name, detail: `عليه ${formatMoney(s.owes)}`, owes: s.owes, learner: s }" />
            </div>
          </div>
          <template v-if="absent.length && store.can('messages.send')" #footer>
            <UButton block color="neutral" variant="outline" icon="i-lucide-message-circle" :label="`بلّغ أولياء أمور الغايبين (${absent.length})`" @click="absentOpen = true" />
          </template>
        </UCard>
      </div>
    </template>

    <UModal v-model:open="absentOpen" title="الغايبين" description="ابعت لولي أمر كل واحد على واتساب.">
      <template #body>
        <div class="divide-y divide-(--ui-border)">
          <div v-for="s in absent" :key="s.learner_id" class="flex items-center gap-3 py-2 text-sm">
            <span class="flex-1 font-bold">{{ s.name }}</span>
            <UButton size="xs" color="success" variant="soft" icon="i-lucide-message-circle" :label="notified.has(s.learner_id) ? 'اتبعت' : 'ابعت'" :disabled="!s.parent_phone" @click="notifyAbsent(s)" />
          </div>
        </div>
      </template>
    </UModal>
  </div>
</template>

<script setup lang="ts">
import type { Roster, RosterStudent, TodaySession } from '~/types/manhagy'

definePageMeta({ module: 'center', permission: 'center.door' })

const api = useApi()
const route = useRoute()
const store = useSessionStore()
const messages = useMessages()
const door = useDoorQueue()

const SAVED = 'manhagy:door-session'
const roster = ref<Roster | null>(null)
const { data: today } = await useAsyncData('door-today', async () => (await api<{ data: TodaySession[] }>('/center/sessions/today')).data)

/** The roster is kept on the device, so the door still knows names and fees offline. */
async function load(sessionId: string) {
  try {
    roster.value = (await api<{ data: Roster }>(`/center/sessions/${sessionId}`)).data
    localStorage.setItem(SAVED, JSON.stringify(roster.value))
  }
  catch (e) {
    const cached = JSON.parse(localStorage.getItem(SAVED) ?? 'null') as Roster | null
    if (cached?.id === sessionId) roster.value = cached
    else throw e
  }
}
async function openSession(id: string) {
  await load(id)
}
async function openGroup(groupId: string) {
  roster.value = (await api<{ data: Roster }>(`/center/groups/${groupId}/open`, { method: 'POST' })).data
  localStorage.setItem(SAVED, JSON.stringify(roster.value))
}
function leave() {
  roster.value = null
  localStorage.removeItem(SAVED)
}
onMounted(async () => {
  if (typeof route.query.group === 'string') await openGroup(route.query.group)
  else if (typeof route.query.session === 'string') await load(route.query.session)
  else {
    const cached = JSON.parse(localStorage.getItem(SAVED) ?? 'null') as Roster | null
    if (cached && Date.parse(cached.starts_at) > Date.now() - 6 * 3600_000) await load(cached.id)
  }
})

const members = computed(() => roster.value?.students.filter(s => s.plan !== 'guest') ?? [])
const presentCount = computed(() => roster.value?.students.filter(s => s.present).length ?? 0)
const absent = computed(() => members.value.filter(s => !s.present))
const search = ref('')
const shown = computed(() => {
  const q = search.value.trim()
  const list = roster.value?.students ?? []
  return q ? list.filter(s => s.name.includes(q) || (s.phone ?? '').includes(q)) : list
})

const flash = ref<{ ok: boolean, title: string, detail?: string, owes?: number, learner?: RosterStudent } | null>(null)

function record(student: RosterStudent | null, scan: { token?: string, learner_id?: string }) {
  if (!roster.value) return
  door.push({ id: deviceUuid(), session_id: roster.value.id, ...scan, scanned_at: new Date().toISOString() })
  if (student) {
    student.present = true
    student.scanned_at = new Date().toISOString()
  }
}

function onRead(value: string) {
  const student = roster.value?.students.find(s => s.token === value) ?? null
  if (!student) {
    record(null, { token: value })
    flash.value = { ok: false, title: 'الكارنيه ده مش في المجموعة', detail: 'اتسجّل وهيتراجع مع السيرفر.' }
    return
  }
  const already = student.present
  record(student, { token: value })
  flash.value = { ok: true, title: student.name, detail: already ? 'متسجّل حضوره قبل كده' : student.owes ? `حضر — عليه ${formatMoney(student.owes)}` : 'حضر ✓', owes: student.owes, learner: student }
}

function mark(student: RosterStudent) {
  record(student, { learner_id: student.learner_id })
  flash.value = { ok: true, title: student.name, detail: student.owes ? `حضر — عليه ${formatMoney(student.owes)}` : 'حضر ✓', owes: student.owes, learner: student }
}

function pay(student: RosterStudent, kind: 'session' | 'month', amount: number) {
  if (!roster.value) return
  door.push({ id: deviceUuid(), session_id: roster.value.id, learner_id: student.learner_id, scanned_at: student.scanned_at ?? new Date().toISOString(), pay: { id: deviceUuid(), kind, amount } })
  student.owes = 0
  student.present = true
  flash.value = { ok: true, title: student.name, detail: `اتدفع ${formatMoney(amount)} ✓` }
}

const absentOpen = ref(false)
const notified = ref(new Set<string>())
function notifyAbsent(s: RosterStudent) {
  messages.send('parent_absence', s.parent_phone, { student: s.name, session: roster.value?.group, link: null }, { type: 'learner', id: s.learner_id })
  notified.value = new Set([...notified.value, s.learner_id])
}
</script>
