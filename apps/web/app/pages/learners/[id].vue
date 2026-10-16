<template>
  <div class="max-w-5xl space-y-6">
    <USkeleton v-if="!learner" class="h-64" />
    <template v-else>
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="flex items-center gap-3">
          <UAvatar :alt="learner.name" size="xl" />
          <div>
            <NuxtLink to="/learners" class="text-sm text-(--ui-text-muted) hover:underline">الطلاب</NuxtLink>
            <h1 class="flex items-center gap-2 text-2xl font-extrabold">
              {{ learner.name }}
              <UBadge v-if="learner.blocked" color="error" variant="subtle" :label="`موقوف: ${learner.blocked_reason}`" />
            </h1>
            <p class="text-sm text-(--ui-text-muted)">
              <span class="num">{{ localPhone(learner.phone) }}</span>
              <template v-if="learner.grade">
                · {{ learner.grade }}
              </template>
              · سجّل {{ formatDate(learner.joined_at) }}
            </p>
          </div>
        </div>
        <div class="flex flex-wrap gap-2">
          <UButton color="success" variant="soft" icon="i-lucide-message-circle" label="واتساب" :to="whatsappLink(learner.phone, `أهلاً يا ${learner.name.split(' ')[0]}`)" target="_blank" />
          <UButton v-if="learner.parent_phone && store.can('reports.parents')" color="neutral" variant="outline" icon="i-lucide-send" label="تقرير لولي الأمر" :loading="sendingReport" @click="sendReport" />
          <template v-if="store.can('learners.manage')">
            <UButton v-if="learner.blocked" color="neutral" variant="outline" icon="i-lucide-user-check" label="رجّعه" @click="unblock" />
            <UButton v-else color="error" variant="soft" icon="i-lucide-user-x" label="أوقفه" @click="block" />
          </template>
        </div>
      </div>

      <div class="grid gap-3 sm:grid-cols-3">
        <StatTile label="رقم ولي الأمر" :value="learner.parent_phone ? localPhone(learner.parent_phone) : '—'" icon="i-lucide-phone" />
        <StatTile label="آخر ظهور" :value="learner.last_seen_at ? timeAgo(learner.last_seen_at) : '—'" icon="i-lucide-clock" />
        <StatTile v-if="wallet" label="رصيد المحفظة" :value="formatMoney(wallet.balance)" icon="i-lucide-wallet" />
      </div>

      <UTabs v-model="tab" :items="tabs" :content="false" class="w-full" />

      <!-- Subscriptions -->
      <UCard v-if="tab === 'enrollments'" :ui="{ body: 'p-0 sm:p-0' }">
        <template #header>
          <div class="flex items-center justify-between">
            <h2 class="font-bold">
              الاشتراكات
            </h2>
            <UButton v-if="store.can('access.enroll')" size="sm" icon="i-lucide-plus" label="اشتراك بالإيد" @click="enrollOpen = true" />
          </div>
        </template>
        <div class="divide-y divide-(--ui-border)">
          <p v-if="!enrollments?.length" class="p-6 text-center text-sm text-(--ui-text-muted)">
            مش مشترك في أي كورس.
          </p>
          <div v-for="e in enrollments" :key="e.id" class="flex flex-wrap items-center gap-3 px-4 py-3 text-sm">
            <span class="flex-1 font-bold">{{ e.course_title }}</span>
            <span class="text-(--ui-text-muted)">{{ ENROLLMENT_SOURCES[e.source] }} · من <span class="num">{{ formatDate(e.starts_at) }}</span><template v-if="e.ends_at"> لحد <span class="num">{{ formatDate(e.ends_at) }}</span></template></span>
            <UBadge v-if="e.revoked_at" color="error" variant="subtle" :label="`اتلغى: ${e.revoked_reason}`" />
            <UBadge v-else :color="e.active ? 'success' : 'neutral'" variant="subtle" :label="e.active ? 'ساري' : 'خلص'" />
            <UButton v-if="e.active && store.can('access.enroll')" size="xs" color="error" variant="ghost" label="إلغاء" @click="revoke(e)" />
          </div>
        </div>
      </UCard>

      <!-- Progress -->
      <div v-else-if="tab === 'progress'" class="space-y-4">
        <p v-if="!progress?.length" class="app-card p-6 text-center text-sm text-(--ui-text-muted)">
          لسه ما اتفرّجش على حاجة.
        </p>
        <UCard v-for="c in progress" :key="c.course_id" :ui="{ body: 'p-0 sm:p-0' }">
          <template #header>
            <div class="flex items-center justify-between">
              <h2 class="font-bold">
                {{ c.title }}
              </h2>
              <span class="text-sm text-(--ui-text-muted)">خلّص <span class="num font-bold">{{ c.lessons.filter(l => l.completed_at).length }}</span> من <span class="num">{{ c.lessons.length }}</span></span>
            </div>
          </template>
          <div class="divide-y divide-(--ui-border)">
            <div v-for="l in c.lessons" :key="l.id" class="flex items-center gap-3 px-4 py-2.5 text-sm">
              <UIcon :name="l.completed_at ? 'i-lucide-circle-check' : 'i-lucide-circle-dashed'" class="size-4" :class="l.completed_at ? 'text-(--ui-success)' : 'text-(--ui-text-muted)'" />
              <span class="flex-1 truncate">{{ l.title }}</span>
              <span v-if="l.watched_seconds" class="num text-(--ui-text-muted)">{{ Math.round(l.watched_seconds / 60) }} د</span>
              <span v-if="l.max_views !== null" class="num" :class="l.views >= l.max_views + l.extra_views ? 'text-(--ui-error) font-bold' : 'text-(--ui-text-muted)'">{{ l.views }} / {{ l.max_views + l.extra_views }}</span>
              <span v-else-if="l.views" class="num text-(--ui-text-muted)">{{ l.views }} مشاهدة</span>
              <UButton v-if="l.max_views !== null && store.can('access.enroll')" size="xs" variant="soft" icon="i-lucide-plus" label="مشاهدة" @click="extraView(l.id)" />
            </div>
          </div>
        </UCard>
      </div>

      <!-- Wallet -->
      <UCard v-else-if="tab === 'wallet' && wallet" :ui="{ body: 'p-0 sm:p-0' }">
        <template #header>
          <div class="flex items-center justify-between">
            <h2 class="font-bold">
              المحفظة: <span class="num">{{ formatMoney(wallet.balance) }}</span>
            </h2>
            <UButton v-if="store.can('wallet.adjust')" size="sm" color="neutral" variant="outline" icon="i-lucide-pencil" label="تعديل الرصيد" @click="adjustOpen = true" />
          </div>
        </template>
        <div class="divide-y divide-(--ui-border)">
          <p v-if="!wallet.transactions.length" class="p-6 text-center text-sm text-(--ui-text-muted)">
            مفيش حركات.
          </p>
          <div v-for="t in wallet.transactions" :key="t.id" class="flex items-center gap-3 px-4 py-2.5 text-sm">
            <span class="flex-1">{{ WALLET_TYPES[t.type] }}<span v-if="t.note" class="text-(--ui-text-muted)"> · {{ t.note }}</span></span>
            <span class="num text-(--ui-text-muted)">{{ formatDate(t.created_at, true) }}</span>
            <span class="num w-24 text-end font-bold" :class="t.amount > 0 ? 'text-(--ui-success)' : ''">{{ t.amount > 0 ? '+' : '' }}{{ formatMoney(t.amount) }}</span>
          </div>
        </div>
      </UCard>

      <!-- Devices + notes -->
      <div v-else-if="tab === 'devices'" class="grid gap-4 lg:grid-cols-2">
        <UCard :ui="{ body: 'p-0 sm:p-0' }">
          <template #header>
            <h2 class="font-bold">
              الأجهزة
            </h2>
          </template>
          <div class="divide-y divide-(--ui-border)">
            <p v-if="!learner.devices.length" class="p-6 text-center text-sm text-(--ui-text-muted)">
              مفيش أجهزة.
            </p>
            <div v-for="d in learner.devices" :key="d.id" class="flex items-center gap-3 px-4 py-3 text-sm">
              <UIcon name="i-lucide-smartphone" class="size-4 text-(--ui-text-muted)" />
              <div class="flex-1">
                <p class="font-bold">
                  {{ d.name }}
                </p>
                <p class="text-xs text-(--ui-text-muted)">
                  اتضاف {{ formatDate(d.added_at) }} · آخر ظهور {{ d.last_seen_at ? timeAgo(d.last_seen_at) : '—' }}
                </p>
              </div>
              <UButton v-if="store.can('learners.devices')" size="xs" color="error" variant="soft" label="فك الجهاز" @click="removeDevice(d.id)" />
            </div>
          </div>
        </UCard>
        <UCard>
          <template #header>
            <h2 class="font-bold">
              ملاحظات
            </h2>
          </template>
          <UTextarea v-model="notes" :rows="5" autoresize class="w-full" placeholder="أي حاجة عايز تفتكرها عن الطالب ده (ما بتظهرش له)" :disabled="!store.can('learners.manage')" />
          <UButton v-if="store.can('learners.manage')" class="mt-3" size="sm" label="حفظ" :loading="savingNotes" @click="saveNotes" />
        </UCard>
      </div>
    </template>

    <UModal v-model:open="enrollOpen" title="اشتراك بالإيد" description="للي دفع كاش أو في السنتر.">
      <template #body>
        <form class="space-y-4" @submit.prevent="enroll">
          <UFormField label="الكورس">
            <USelect v-model="enrollForm.course_id" :items="courseItems" class="w-full" placeholder="اختار كورس" />
          </UFormField>
          <UFormField label="المدة بالأيام" hint="اختياري" description="فاضي = مدة الكورس نفسه.">
            <UInputNumber v-model="enrollForm.days" :min="1" :max="1000" class="w-full" />
          </UFormField>
          <div class="flex justify-end gap-2">
            <UButton color="neutral" variant="ghost" label="إلغاء" @click="enrollOpen = false" />
            <UButton type="submit" :disabled="!enrollForm.course_id" :loading="enrolling" label="افتح الكورس" />
          </div>
        </form>
      </template>
    </UModal>

    <UModal v-model:open="adjustOpen" title="تعديل الرصيد" description="زوّد أو اخصم بالإيد، والسبب بيتسجّل في سجل العمليات.">
      <template #body>
        <form class="space-y-4" @submit.prevent="adjust">
          <div class="flex gap-1 rounded-(--ui-radius) bg-(--ui-bg-elevated) p-1">
            <UButton class="flex-1 justify-center" :color="adjustForm.sign > 0 ? 'primary' : 'neutral'" :variant="adjustForm.sign > 0 ? 'solid' : 'ghost'" label="زوّد" @click="adjustForm.sign = 1" />
            <UButton class="flex-1 justify-center" :color="adjustForm.sign < 0 ? 'primary' : 'neutral'" :variant="adjustForm.sign < 0 ? 'solid' : 'ghost'" label="اخصم" @click="adjustForm.sign = -1" />
          </div>
          <UFormField label="المبلغ (ج)">
            <UInputNumber v-model="adjustForm.pounds" :min="1" class="w-full" />
          </UFormField>
          <UFormField label="السبب">
            <UInput v-model="adjustForm.note" class="w-full" placeholder="هدية للأوائل / تصحيح غلط" />
          </UFormField>
          <div class="flex justify-end gap-2">
            <UButton color="neutral" variant="ghost" label="إلغاء" @click="adjustOpen = false" />
            <UButton type="submit" :disabled="!adjustForm.pounds || !adjustForm.note" label="سجّل" />
          </div>
        </form>
      </template>
    </UModal>
  </div>
</template>

<script setup lang="ts">
import type { CourseProgress, CourseSummary, Enrollment, LearnerDetail, WalletTransaction } from '~/types/manhagy'

definePageMeta({ permission: 'learners.view' })

const route = useRoute()
const api = useApi()
const store = useSessionStore()
const toast = useToast()
const messages = useMessages()
const id = String(route.params.id)

const learner = ref<LearnerDetail | null>(null)
const notes = ref('')
async function loadLearner() {
  learner.value = (await api<{ data: LearnerDetail }>(`/learners/${id}`)).data
  notes.value = learner.value.notes ?? ''
}
await loadLearner()
const learnerId = computed(() => learner.value?.learner_id ?? '')

const tabs = computed(() => [
  { label: 'الاشتراكات', value: 'enrollments', icon: 'i-lucide-book-open-text' },
  { label: 'التقدّم', value: 'progress', icon: 'i-lucide-chart-line' },
  ...(store.can('wallet.view') ? [{ label: 'المحفظة', value: 'wallet', icon: 'i-lucide-wallet' }] : []),
  { label: 'الأجهزة والملاحظات', value: 'devices', icon: 'i-lucide-smartphone' },
])
const tab = ref('enrollments')

const WALLET_TYPES: Record<WalletTransaction['type'], string> = { code: 'شحن بكود', transfer: 'شحن بتحويل', purchase: 'شراء كورس', adjustment: 'تعديل بالإيد' }

const { data: enrollments, refresh: refreshEnrollments } = await useAsyncData(`learner-enrollments-${id}`, async () => (await api<{ data: Enrollment[] }>('/enrollments', { query: { learner_id: learnerId.value } })).data)
const { data: progress, refresh: refreshProgress } = await useAsyncData(`learner-progress-${id}`, async () => (await api<{ data: CourseProgress[] }>('/access/progress', { query: { learner_id: learnerId.value } })).data)
const { data: wallet, refresh: refreshWallet } = await useAsyncData(`learner-wallet-${id}`, async () => store.can('wallet.view')
  ? (await api<{ data: { balance: number, transactions: WalletTransaction[] } }>('/wallet/statement', { query: { learner_id: learnerId.value } })).data
  : null)

async function act(fn: () => Promise<unknown>, ok?: string) {
  try {
    await fn()
    if (ok) toast.add({ color: 'success', title: ok })
    return true
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
    return false
  }
}

async function block() {
  const reason = prompt('سبب الإيقاف (بيتسجّل وبيظهر للطالب لو كلّمك):')
  if (reason?.trim()) await act(async () => (learner.value = (await api<{ data: LearnerDetail }>(`/learners/${id}/block`, { method: 'POST', body: { reason } })).data), 'الطالب اتوقف')
}
async function unblock() {
  await act(async () => (learner.value = (await api<{ data: LearnerDetail }>(`/learners/${id}/unblock`, { method: 'POST' })).data), 'الطالب رجع')
}
async function removeDevice(device: string) {
  if (confirm('تفك الجهاز ده؟ الطالب هيخرج منه ويقدر يدخل من جهاز تاني.')) {
    await act(async () => (learner.value = (await api<{ data: LearnerDetail }>(`/learners/${id}/devices/${device}`, { method: 'DELETE' })).data), 'الجهاز اتفك')
  }
}
const savingNotes = ref(false)
async function saveNotes() {
  savingNotes.value = true
  await act(() => api(`/learners/${id}`, { method: 'PATCH', body: { notes: notes.value || null } }), 'اتحفظت')
  savingNotes.value = false
}

const enrollOpen = ref(false)
const enrolling = ref(false)
const enrollForm = reactive({ course_id: '', days: null as number | null })
const { data: courses } = await useAsyncData('courses-for-enroll', async () => store.can('access.enroll') ? (await api<{ data: CourseSummary[] }>('/courses')).data : [])
const courseItems = computed(() => (courses.value ?? []).filter(c => c.status !== 'archived').map(c => ({ value: c.id, label: c.title })))
async function enroll() {
  enrolling.value = true
  if (await act(() => api('/enrollments', { method: 'POST', body: { learner_id: learnerId.value, course_id: enrollForm.course_id, days: enrollForm.days || null } }), 'الكورس اتفتح للطالب')) {
    enrollOpen.value = false
    await refreshEnrollments()
  }
  enrolling.value = false
}
async function revoke(e: Enrollment) {
  const reason = prompt(`سبب إلغاء اشتراكه في «${e.course_title}»:`)
  if (reason?.trim() && await act(() => api(`/enrollments/${e.id}/revoke`, { method: 'POST', body: { reason } }), 'الاشتراك اتلغى')) await refreshEnrollments()
}
async function extraView(lesson: string) {
  if (await act(() => api('/access/extra-views', { method: 'POST', body: { learner_id: learnerId.value, lesson_id: lesson, views: 1 } }), 'اتزوّدت مشاهدة')) await refreshProgress()
}

const adjustOpen = ref(false)
const adjustForm = reactive({ sign: 1, pounds: 0, note: '' })
async function adjust() {
  if (await act(() => api('/wallet/adjust', { method: 'POST', body: { learner_id: learnerId.value, amount: adjustForm.sign * Math.round(adjustForm.pounds * 100), note: adjustForm.note } }), 'الرصيد اتعدّل')) {
    adjustOpen.value = false
    Object.assign(adjustForm, { sign: 1, pounds: 0, note: '' })
    await refreshWallet()
  }
}

const sendingReport = ref(false)
async function sendReport() {
  sendingReport.value = true
  await act(async () => {
    const report = (await api<{ data: { message: Record<string, string | null> } }>(`/parent-reports/${learnerId.value}`)).data
    messages.send('parent_report', learner.value!.parent_phone, report.message, { type: 'learner', id: learnerId.value })
  })
  sendingReport.value = false
}
</script>
