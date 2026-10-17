<template>
  <div class="space-y-6">
    <USkeleton v-if="!course" class="h-64" />
    <template v-else>
      <div class="app-card space-y-3 p-5">
        <p v-if="course.grade" class="text-xs text-(--ui-text-muted)">
          {{ course.grade }}
        </p>
        <h1 class="text-2xl font-extrabold">
          {{ course.title }}
        </h1>
        <p v-if="course.description" class="whitespace-pre-line text-(--ui-text-muted)">
          {{ course.description }}
        </p>
        <div v-if="course.enrolled" class="flex flex-wrap items-center gap-2 text-sm">
          <UBadge color="success" variant="subtle" icon="i-lucide-circle-check" label="مشترك" />
          <span v-if="course.ends_at" class="text-(--ui-text-muted)">لحد {{ formatDate(course.ends_at) }}</span>
          <UButton v-if="course.ends_at && course.access_days" size="xs" variant="link" label="جدّد" @click="buyOpen = true" />
        </div>
        <div v-else class="flex flex-wrap items-center gap-2">
          <span class="num text-2xl font-extrabold text-primary">{{ course.price ? formatMoney(course.price) : 'مجاني' }}</span>
          <span v-if="course.access_days" class="text-sm text-(--ui-text-muted)">لمدة {{ course.access_days === 30 ? 'شهر' : `${course.access_days} يوم` }}</span>
          <div class="ms-auto flex gap-2">
            <UButton to="/redeem" color="neutral" variant="outline" icon="i-lucide-ticket" label="عندي كود" />
            <UButton icon="i-lucide-shopping-cart" :label="course.price ? 'اشترك من المحفظة' : 'اشترك مجاناً'" @click="course.price ? (buyOpen = true) : buy()" />
          </div>
        </div>
      </div>

      <div v-for="chapter in course.chapters" :key="chapter.id" class="space-y-2">
        <h2 class="font-bold">
          {{ chapter.title }}
        </h2>
        <div class="app-card divide-y divide-(--ui-border) overflow-hidden">
          <component
            :is="closed(lesson) ? 'div' : NuxtLink"
            v-for="lesson in chapter.lessons"
            :key="lesson.id"
            :to="closed(lesson) ? undefined : target(lesson)"
            class="flex items-center gap-3 px-4 py-3"
            :class="closed(lesson) ? 'opacity-60' : 'transition hover:bg-(--ui-bg-elevated)/60'"
          >
            <span class="grid size-10 shrink-0 place-items-center rounded-full" :class="lesson.completed ? 'bg-(--ui-success)/15 text-(--ui-success)' : 'bg-(--ui-bg-elevated)'">
              <UIcon :name="closed(lesson) ? 'i-lucide-lock' : lesson.completed ? 'i-lucide-check' : KIND_ICON[lesson.kind]" class="size-5" />
            </span>
            <span class="min-w-0 flex-1">
              <span class="block truncate font-bold">{{ lesson.title }}</span>
              <span class="flex flex-wrap gap-x-2 text-xs text-(--ui-text-muted)">
                <span v-if="lesson.duration_seconds" class="num">{{ formatDuration(lesson.duration_seconds) }}</span>
                <span v-if="lesson.kind === 'live' && lesson.locked === 'not_yet' && lesson.publish_at" class="font-bold text-error">لايف {{ formatDate(lesson.publish_at, true) }}</span>
                <span v-else-if="lesson.locked === 'not_yet' && lesson.publish_at">يفتح {{ formatDate(lesson.publish_at, true) }}</span>
                <span v-else-if="lesson.locked">{{ LOCK_TEXT[lesson.locked] }}</span>
                <span v-if="lesson.views_left !== null && !lesson.locked">فاضل <span class="num">{{ lesson.views_left }}</span> مشاهدة</span>
              </span>
            </span>
            <UBadge v-if="lesson.free_preview && !course.enrolled" size="sm" color="success" variant="subtle" label="مجاني" />
            <UIcon v-if="!closed(lesson)" name="i-lucide-chevron-left" class="size-4 text-(--ui-text-muted)" />
          </component>
        </div>
      </div>

      <template v-if="course.enrolled">
        <CourseCertificate :course-id="course.id" />
        <CourseReview :course-id="course.id" />
      </template>
    </template>

    <UModal v-model:open="buyOpen" title="اشترك من المحفظة">
      <template #body>
        <div v-if="course" class="space-y-4">
          <p>«{{ course.title }}» بـ <b class="num">{{ formatMoney(course.price) }}</b>. رصيدك <b class="num">{{ formatMoney(balance ?? 0) }}</b>.</p>
          <UAlert v-if="(balance ?? 0) < course.price" color="warning" variant="subtle" title="رصيدك مش كفاية" description="اشحن محفظتك بكود رصيد أو بتحويل الأول." :actions="[{ label: 'اشحن', to: '/wallet' }]" />
          <UAlert v-if="error" color="error" variant="subtle" :title="error" />
          <UButton block size="lg" :disabled="(balance ?? 0) < course.price" :loading="buying" label="ادفع واشترك" @click="buy" />
        </div>
      </template>
    </UModal>
  </div>
</template>

<script setup lang="ts">
import type { CourseOutline, OutlineLesson } from '~/utils/types'

definePageMeta({ auth: true })

const NuxtLink = resolveComponent('NuxtLink')
const KIND_ICON = { video: 'i-lucide-play', pdf: 'i-lucide-file-text', exam: 'i-lucide-list-checks', live: 'i-lucide-radio' } as const

/** A live lesson before its time still opens: the waiting room (or the teacher started early). */
function closed(lesson: OutlineLesson): boolean {
  return lesson.locked !== null && !(lesson.kind === 'live' && lesson.locked === 'not_yet')
}

function target(lesson: OutlineLesson): string {
  if (lesson.kind === 'exam') return `/exams/${lesson.id}`
  if (lesson.kind === 'live' && lesson.locked === 'not_yet') return `/live/${lesson.id}`
  return `/lessons/${lesson.id}?course=${course.value?.id}`
}
const api = useLearnApi()
const toast = useToast()
const id = String(useRoute().params.id)
const { data: course, refresh } = await useAsyncData(`course-${id}`, async () => (await api<{ data: CourseOutline }>(`/learner/courses/${id}`)).data)

const buyOpen = ref(false)
const buying = ref(false)
const error = ref<string | null>(null)
const { data: balance, refresh: refreshBalance } = await useAsyncData('balance', async () => (await api<{ data: { balance: number } }>('/learner/wallet')).data.balance)
watch(buyOpen, open => open && refreshBalance())

async function buy() {
  buying.value = true
  error.value = null
  try {
    await api(`/learner/courses/${id}/buy`, { method: 'POST' })
    buyOpen.value = false
    toast.add({ color: 'success', title: 'اشتركت 🎉 يلا نبدأ' })
    await refresh()
  }
  catch (e) {
    error.value = apiMessage(e)
    if (!buyOpen.value) toast.add({ color: 'error', title: error.value })
  }
  finally {
    buying.value = false
  }
}
</script>
