<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold">
        أهلاً {{ store.session?.user.name }} 👋
      </h1>
      <p class="text-(--ui-text-muted)">
        {{ today }}
      </p>
    </div>

    <!-- The owner's first steps until each is done. -->
    <UCard v-if="steps.length" :ui="{ body: 'space-y-3' }">
      <template #header>
        <h2 class="font-bold">
          ابدأ من هنا
        </h2>
      </template>
      <NuxtLink v-for="step in steps" :key="step.to" :to="step.to" class="flex items-center gap-3 rounded-(--ui-radius) p-2 transition hover:bg-(--ui-bg-elevated)">
        <UIcon :name="step.done ? 'i-lucide-circle-check' : 'i-lucide-circle-dashed'" class="size-5" :class="step.done ? 'text-(--ui-success)' : 'text-(--ui-text-muted)'" />
        <span class="flex-1" :class="{ 'line-through text-(--ui-text-muted)': step.done }">{{ step.label }}</span>
        <UIcon name="i-lucide-chevron-left" class="size-4 text-(--ui-text-muted)" />
      </NuxtLink>
    </UCard>

    <UAlert
      v-if="showTwoFactorNudge"
      color="warning"
      variant="subtle"
      icon="i-lucide-shield-alert"
      title="احمِ حسابك بالتحقق بخطوتين"
      description="كود من موبايلك مع كلمة السر، عشان محدش يدخل على كورساتك وفلوسك لو عرف كلمة السر."
      :actions="[{ label: 'فعّله دلوقتي', to: '/settings/security', color: 'warning' }, { label: 'بعدين', color: 'neutral', variant: 'ghost', onClick: dismissTwoFactorNudge }]"
    />

    <InstallCard />

    <div v-if="actions.length" class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
      <NuxtLink
        v-for="action in actions"
        :key="action.label"
        :to="action.to"
        class="app-card group flex items-center gap-3 p-3 text-start transition hover:ring-1 hover:ring-primary"
      >
        <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-(--app-primary-soft) text-(--app-primary-strong)">
          <UIcon :name="action.icon" class="size-5" />
        </span>
        <span class="min-w-0">
          <span class="block truncate font-bold">{{ action.label }}</span>
          <span class="block truncate text-xs text-(--ui-text-muted)">{{ action.description }}</span>
        </span>
      </NuxtLink>
    </div>

    <template v-if="dash">
      <div v-if="dash.money" class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <StatTile label="دخل النهارده" :value="formatMoney(dash.money.today.total)" :hint="`${dash.money.today.codes_count} كود · تحويلات ${formatMoney(dash.money.today.transfers)}`" icon="i-lucide-banknote" />
        <StatTile label="دخل الشهر" :value="formatMoney(dash.money.month.total)" :hint="`أكواد ${formatMoney(dash.money.month.codes)} · تحويلات ${formatMoney(dash.money.month.transfers)}`" icon="i-lucide-calendar" />
        <StatTile label="طلاب مشتركين" :value="String(dash.students.enrolled)" :hint="`من ${dash.students.total} طالب مسجّل`" icon="i-lucide-users-round" />
        <StatTile label="طلاب جداد" :value="String(dash.students.new_today)" :hint="`${dash.students.new_month} الشهر ده`" icon="i-lucide-user-plus" />
      </div>
      <div v-else class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <StatTile label="طلاب مشتركين" :value="String(dash.students.enrolled)" :hint="`من ${dash.students.total} طالب مسجّل`" icon="i-lucide-users-round" />
        <StatTile label="طلاب جداد" :value="String(dash.students.new_today)" :hint="`${dash.students.new_month} الشهر ده`" icon="i-lucide-user-plus" />
        <StatTile label="نشطين الأسبوع ده" :value="String(dash.students.active_week)" icon="i-lucide-activity" />
        <StatTile label="بيتفرّجوا دلوقتي" :value="String(dash.watching.watching_now)" icon="i-lucide-play" />
      </div>

      <div class="grid gap-4 lg:grid-cols-3">
        <UCard class="lg:col-span-2">
          <template #header>
            <div class="flex items-center justify-between">
              <h2 class="font-bold">
                المشاهدة
              </h2>
              <span class="text-sm text-(--ui-text-muted)"><span class="num">{{ dash.watching.watching_now }}</span> بيتفرّجوا دلوقتي</span>
            </div>
          </template>
          <div class="grid grid-cols-3 gap-3 text-center">
            <div>
              <p class="num text-2xl font-extrabold">
                {{ dash.watching.views_today }}
              </p>
              <p class="text-xs text-(--ui-text-muted)">
                مشاهدة النهارده
              </p>
            </div>
            <div>
              <p class="num text-2xl font-extrabold">
                {{ dash.watching.views_month }}
              </p>
              <p class="text-xs text-(--ui-text-muted)">
                مشاهدة الشهر ده
              </p>
            </div>
            <div>
              <p class="num text-2xl font-extrabold">
                {{ dash.watching.hours_month }}
              </p>
              <p class="text-xs text-(--ui-text-muted)">
                ساعة مشاهدة
              </p>
            </div>
          </div>
          <div v-if="dash.watching.top_lessons.length" class="mt-4 space-y-2">
            <p class="text-sm font-bold">
              أكتر الدروس مشاهدة الشهر ده
            </p>
            <div v-for="l in dash.watching.top_lessons" :key="l.lesson_id" class="flex items-center gap-3 text-sm">
              <span class="flex-1 truncate">{{ l.title }} <span class="text-(--ui-text-muted)">· {{ l.course }}</span></span>
              <div class="h-2 w-32 overflow-hidden rounded-full bg-(--ui-bg-elevated)">
                <div class="h-full rounded-full bg-(--app-chart)" :style="{ width: `${Math.round(l.views / topViews * 100)}%` }" />
              </div>
              <span class="num w-10 text-end font-bold">{{ l.views }}</span>
            </div>
          </div>
          <p v-else class="mt-4 text-sm text-(--ui-text-muted)">
            لسه مفيش مشاهدات الشهر ده.
          </p>
        </UCard>

        <UCard>
          <template #header>
            <h2 class="font-bold">
              محتاج منك
            </h2>
          </template>
          <div class="space-y-2">
            <NuxtLink v-if="dash.waiting.transfers !== undefined" to="/wallet/topups" class="flex items-center justify-between rounded-(--ui-radius) p-2 hover:bg-(--ui-bg-elevated)">
              <span class="flex items-center gap-2"><UIcon name="i-lucide-wallet" class="size-4" /> تحويلات مستنية تأكيد</span>
              <UBadge :color="dash.waiting.transfers ? 'warning' : 'neutral'" variant="subtle" class="num">{{ dash.waiting.transfers }}</UBadge>
            </NuxtLink>
            <div class="flex items-center justify-between rounded-(--ui-radius) p-2">
              <span class="flex items-center gap-2"><UIcon name="i-lucide-list-checks" class="size-4" /> امتحانات الأسبوع ده</span>
              <span class="num font-bold">{{ dash.exams.attempts_week }}<span v-if="dash.exams.average_percent_week !== null" class="text-(--ui-text-muted) font-normal"> · متوسط {{ dash.exams.average_percent_week }}%</span></span>
            </div>
            <div class="flex items-center justify-between rounded-(--ui-radius) p-2">
              <span class="flex items-center gap-2"><UIcon name="i-lucide-timer" class="size-4" /> بيمتحنوا دلوقتي</span>
              <span class="num font-bold">{{ dash.exams.in_progress }}</span>
            </div>
          </div>
        </UCard>
      </div>

      <UCard v-if="dash.sharing.length">
        <template #header>
          <div>
            <h2 class="font-bold">
              حسابات شكلها متشاركة
            </h2>
            <p class="text-sm text-(--ui-text-muted)">
              اتفرّجوا من أكتر من شبكة أو جهاز في آخر 24 ساعة. ممكن يكون طبيعي (البيت والسنتر)، وممكن حد بيدّي حسابه لغيره.
            </p>
          </div>
        </template>
        <div class="divide-y divide-(--ui-border)">
          <div v-for="s in dash.sharing" :key="s.learner_id" class="flex items-center justify-between gap-3 py-2 text-sm">
            <span class="font-bold">{{ s.name }}</span>
            <span class="text-(--ui-text-muted)"><span class="num">{{ s.networks }}</span> شبكة · <span class="num">{{ s.devices }}</span> جهاز · <span class="num">{{ s.sessions }}</span> مشاهدة</span>
          </div>
        </div>
      </UCard>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { CourseSummary, Dashboard } from '~/types/manhagy'

const store = useSessionStore()
const api = useApi()
const actions = computed(() => useQuickActions().value.filter(a => a.to).slice(0, 5))

const today = new Date().toLocaleDateString('ar-EG-u-nu-latn', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })

const { data: dash } = await useAsyncData('dashboard', async () => store.can('reports.view')
  ? (await api<{ data: Dashboard }>('/dashboard')).data
  : null)
const topViews = computed(() => Math.max(1, ...(dash.value?.watching.top_lessons.map(l => l.views) ?? [1])))

// «ابدأ من هنا» for the owner: the platform's address, a course, a published course, codes, an assistant.
const { data: setup } = await useAsyncData('setup-steps', async () => {
  if (!store.isOwner) {
    return null
  }
  const [site, courses] = await Promise.all([
    api<{ data: { slug: string } }>('/site').then(r => r.data).catch(() => null),
    api<{ data: CourseSummary[] }>('/courses').then(r => r.data).catch(() => [] as CourseSummary[]),
  ])
  return { site, courses }
})
const steps = computed(() => {
  if (!setup.value) {
    return []
  }
  const { site, courses } = setup.value
  const list = [
    { label: 'اختار لينك منصتك واسمها', to: '/settings/site', done: !!site && !site.slug.startsWith('t-') },
    { label: 'اعمل أول كورس', to: '/courses?new=1', done: courses.length > 0 },
    { label: 'ارفع درس وانشر الكورس', to: courses[0] ? `/courses/${courses[0].id}` : '/courses', done: courses.some(c => c.status === 'published') },
    { label: 'اعمل دفعة أكواد للمكتبات', to: '/codes/new', done: false },
  ]
  return list.every(s => s.done) ? [] : list.slice(0, 3).some(s => !s.done) ? list.slice(0, 3) : []
})

const NUDGE_KEY = 'manhagy:2fa-nudge-dismissed'
const nudgeDismissedAt = ref<number>(0)
onMounted(() => {
  nudgeDismissedAt.value = Number(localStorage.getItem(NUDGE_KEY) ?? 0)
})
const showTwoFactorNudge = computed(() => store.isOwner
  && steps.value.length === 0
  && store.session?.user.two_factor_enabled === false
  && Date.now() - nudgeDismissedAt.value > 7 * 24 * 60 * 60 * 1000)
function dismissTwoFactorNudge() {
  nudgeDismissedAt.value = Date.now()
  localStorage.setItem(NUDGE_KEY, String(nudgeDismissedAt.value))
}
</script>
