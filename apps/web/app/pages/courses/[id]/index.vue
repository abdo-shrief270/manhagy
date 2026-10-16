<template>
  <div class="space-y-6">
    <USkeleton v-if="!course" class="h-96" />
    <template v-else>
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
          <NuxtLink to="/courses" class="text-sm text-(--ui-text-muted) hover:underline">الكورسات</NuxtLink>
          <h1 class="flex items-center gap-2 text-2xl font-extrabold">
            {{ course.title }}
            <UBadge :color="COURSE_STATUS[course.status].color" variant="subtle" :label="COURSE_STATUS[course.status].label" />
          </h1>
          <p class="text-sm text-(--ui-text-muted)">
            {{ course.grade }}
          </p>
        </div>
        <div v-if="canManage" class="flex flex-wrap gap-2">
          <UButton v-if="course.status !== 'published'" icon="i-lucide-send" label="انشر الكورس" :loading="busy" @click="setStatus('published')" />
          <UButton v-if="course.status === 'published'" color="neutral" variant="outline" icon="i-lucide-eye-off" label="رجّعه مسودة" :loading="busy" @click="setStatus('draft')" />
          <UDropdownMenu :items="menu">
            <UButton color="neutral" variant="ghost" icon="i-lucide-ellipsis-vertical" square aria-label="المزيد" />
          </UDropdownMenu>
        </div>
      </div>

      <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
          <UCard v-for="(chapter, ci) in course.chapters" :key="chapter.id" :ui="{ body: 'p-0 sm:p-0' }">
            <template #header>
              <div class="flex items-center gap-2">
                <UIcon name="i-lucide-folder" class="size-5 text-(--ui-primary)" />
                <h2 class="flex-1 font-bold">
                  {{ chapter.title }}
                </h2>
                <template v-if="canManage">
                  <UButton size="xs" color="neutral" variant="ghost" icon="i-lucide-pencil" aria-label="تغيير اسم الفصل" @click="renameChapter(chapter)" />
                  <UButton v-if="!chapter.lessons.length && course.chapters.length > 1" size="xs" color="error" variant="ghost" icon="i-lucide-trash-2" aria-label="امسح الفصل" @click="deleteChapter(chapter)" />
                </template>
              </div>
            </template>
            <div class="divide-y divide-(--ui-border)">
              <div v-for="(lesson, li) in chapter.lessons" :key="lesson.id" class="flex items-center gap-3 px-4 py-3">
                <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-(--ui-bg-elevated)">
                  <UIcon :name="LESSON_KINDS[lesson.kind].icon" class="size-4" />
                </span>
                <div class="min-w-0 flex-1">
                  <p class="truncate font-bold">
                    {{ lesson.title }}
                  </p>
                  <div class="flex flex-wrap gap-1 text-xs">
                    <span v-if="lesson.duration_seconds" class="num text-(--ui-text-muted)">{{ formatDuration(lesson.duration_seconds) }}</span>
                    <UBadge v-if="lesson.media && lesson.media.status !== 'ready'" size="sm" color="warning" variant="subtle" label="الفيديو بيتجهّز" />
                    <UBadge v-if="lesson.free_preview" size="sm" color="success" variant="subtle" label="مجاني للكل" />
                    <UBadge v-if="lesson.requires_previous" size="sm" color="neutral" variant="subtle" icon="i-lucide-lock" label="بعد اللي قبله" />
                    <UBadge v-if="lesson.max_views" size="sm" color="neutral" variant="subtle" :label="`${lesson.max_views} مشاهدات`" />
                    <UBadge v-if="lesson.publish_at && new Date(lesson.publish_at) > new Date()" size="sm" color="primary" variant="subtle" icon="i-lucide-calendar-clock" :label="`يفتح ${formatDate(lesson.publish_at, true)}`" />
                  </div>
                </div>
                <UButton v-if="lesson.kind === 'exam' && store.can('assessments.manage')" size="sm" variant="soft" icon="i-lucide-list-checks" label="الامتحان" :to="`/exams/${lesson.id}`" />
                <div v-if="canManage" class="flex shrink-0 items-center">
                  <UButton size="xs" color="neutral" variant="ghost" icon="i-lucide-arrow-up" aria-label="لفوق" :disabled="ci === 0 && li === 0" @click="move(lesson, -1)" />
                  <UButton size="xs" color="neutral" variant="ghost" icon="i-lucide-arrow-down" aria-label="لتحت" :disabled="ci === course.chapters.length - 1 && li === chapter.lessons.length - 1" @click="move(lesson, 1)" />
                  <UButton size="xs" color="neutral" variant="ghost" icon="i-lucide-pencil" aria-label="تعديل" @click="editLesson(lesson)" />
                  <UButton size="xs" color="error" variant="ghost" icon="i-lucide-trash-2" aria-label="مسح" @click="deleteLesson(lesson)" />
                </div>
              </div>
              <div v-if="canManage" class="px-4 py-2">
                <UButton size="sm" variant="ghost" icon="i-lucide-plus" label="درس جديد" @click="newLesson(chapter.id)" />
              </div>
            </div>
          </UCard>
          <UButton v-if="canManage" color="neutral" variant="outline" icon="i-lucide-folder-plus" label="فصل جديد" @click="addChapter" />
        </div>

        <div class="space-y-4">
        <UCard v-if="details" :ui="{ body: 'space-y-4' }">
          <template #header>
            <h2 class="font-bold">
              بيانات الكورس
            </h2>
          </template>
          <UFormField label="الاسم">
            <UInput v-model="details.title" class="w-full" :disabled="!canManage" />
          </UFormField>
          <UFormField label="وصف" hint="اختياري">
            <UTextarea :model-value="details.description ?? ''" @update:model-value="v => details!.description = v" :rows="3" autoresize class="w-full" :disabled="!canManage" placeholder="الكورس بيشرح إيه وبيبدأ إمتى" />
          </UFormField>
          <UFormField label="السعر (ج)" description="0 = مجاني">
            <UInputNumber v-model="details.pounds" :min="0" class="w-full" :disabled="!canManage" />
          </UFormField>
          <UFormField label="مدة الاشتراك بالأيام" description="فاضي = لحد ما تقفل الكورس. الكود يقدر يحدد مدة تانية.">
            <UInputNumber v-model="details.access_days" :min="1" :max="1000" class="w-full" :disabled="!canManage" placeholder="لحد ما يقفل" />
          </UFormField>
          <UButton v-if="canManage" block :loading="savingDetails" label="حفظ البيانات" @click="saveDetails" />
          <div class="space-y-1 border-t border-(--ui-border) pt-3 text-sm text-(--ui-text-muted)">
            <p><span class="num font-bold text-(--ui-text)">{{ course.lessons_count }}</span> درس · <span class="num font-bold text-(--ui-text)">{{ course.free_previews }}</span> مجاني للكل</p>
            <p v-if="course.published_at">
              اتنشر {{ formatDate(course.published_at) }}
            </p>
          </div>
          <div class="flex flex-wrap gap-2">
            <UButton v-if="store.can('codes.manage')" size="sm" color="neutral" variant="outline" icon="i-lucide-ticket" label="أكواد للكورس ده" :to="`/codes/new?course=${course.id}`" />
            <UButton v-if="store.can('courses.view')" size="sm" color="neutral" variant="outline" icon="i-lucide-users-round" label="المشتركين" :to="`/courses/${course.id}/students`" />
          </div>
        </UCard>
        <CertificateSettingsCard v-if="store.can('certificates.manage')" :course-id="course.id" />
        </div>
      </div>
    </template>

    <UModal v-model:open="lessonOpen" :title="lessonForm.id ? 'تعديل الدرس' : 'درس جديد'" :ui="{ content: 'sm:max-w-xl' }">
      <template #body>
        <form class="space-y-4" @submit.prevent="saveLesson">
          <div v-if="!lessonForm.id" class="grid grid-cols-3 gap-2">
            <button
              v-for="(k, key) in LESSON_KINDS"
              :key="key"
              type="button"
              class="flex flex-col items-center gap-1 rounded-(--ui-radius) border p-3 text-sm font-bold transition"
              :class="lessonForm.kind === key ? 'border-primary bg-(--app-primary-soft)' : 'border-(--ui-border)'"
              @click="lessonForm.kind = key; lessonForm.media_id = null"
            >
              <UIcon :name="k.icon" class="size-5" />
              {{ k.label }}
            </button>
          </div>
          <UFormField label="اسم الدرس">
            <UInput v-model="lessonForm.title" class="w-full" placeholder="الحصة 1 — قانون أوم" />
          </UFormField>
          <UFormField v-if="lessonForm.kind !== 'exam'" :label="lessonForm.kind === 'video' ? 'الفيديو' : 'الملف'">
            <MediaPicker :key="lessonForm.kind" v-model="lessonForm.media_id" :kind="lessonForm.kind === 'video' ? 'video' : 'pdf'" />
          </UFormField>
          <p v-else class="rounded-(--ui-radius) bg-(--ui-bg-elevated) p-3 text-sm text-(--ui-text-muted)">
            بعد ما تحفظ الدرس، دوس «الامتحان» جنبه عشان تختار الأسئلة والوقت.
          </p>

          <div class="space-y-3 rounded-(--ui-radius) border border-(--ui-border) p-3">
            <USwitch v-model="lessonForm.free_preview" label="مجاني للكل" description="أي طالب مسجّل يشوفه من غير اشتراك — زي الحصة الأولى." />
            <USwitch v-model="lessonForm.requires_previous" label="ما يفتحش غير بعد الدرس اللي قبله" description="الطالب لازم يخلّص اللي قبله (يشوفه أو يسلّم الامتحان)." />
            <div class="flex flex-wrap items-center gap-3">
              <USwitch v-model="limitViews" label="عدد مرات المشاهدة محدود" />
              <UInputNumber v-if="limitViews" v-model="lessonForm.max_views" :min="1" :max="100" class="w-28" />
            </div>
            <UFormField v-if="lessonForm.kind === 'video'" label="المشاهدة تتحسب بعد" :description="`لما الطالب يتفرّج على ${lessonForm.count_percent}% من الفيديو.`">
              <USlider v-model="lessonForm.count_percent" :min="10" :max="100" :step="5" />
            </UFormField>
            <UFormField label="يفتح يوم" hint="اختياري" description="فاضي = مع الكورس.">
              <UInput v-model="lessonForm.publish_at" type="datetime-local" class="w-full" />
            </UFormField>
          </div>

          <UAlert v-if="lessonError" color="error" variant="subtle" :title="lessonError" />
          <div class="flex justify-end gap-2">
            <UButton color="neutral" variant="ghost" label="إلغاء" @click="lessonOpen = false" />
            <UButton type="submit" :loading="savingLesson" label="حفظ" />
          </div>
        </form>
      </template>
    </UModal>
  </div>
</template>

<script setup lang="ts">
import type { DropdownMenuItem } from '@nuxt/ui'
import type { Chapter, CourseDetail, Lesson, LessonKind } from '~/types/manhagy'

definePageMeta({ permission: 'courses.view' })

const route = useRoute()
const api = useApi()
const store = useSessionStore()
const toast = useToast()
const id = String(route.params.id)
const canManage = computed(() => store.can('courses.manage'))

const course = ref<CourseDetail | null>(null)
const details = ref<{ title: string, description: string | null, pounds: number, access_days: number | null } | null>(null)
const busy = ref(false)

function fill(data: CourseDetail) {
  course.value = data
  details.value = { title: data.title, description: data.description, pounds: data.price / 100, access_days: data.access_days }
}
onMounted(async () => fill((await api<{ data: CourseDetail }>(`/courses/${id}`)).data))

async function call(path: string, method: 'POST' | 'PATCH' | 'PUT' | 'DELETE', body?: Record<string, unknown>) {
  try {
    fill((await api<{ data: CourseDetail }>(`/courses/${id}${path}`, { method, body })).data)
    return true
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
    return false
  }
}

async function setStatus(status: CourseDetail['status']) {
  busy.value = true
  if (await call('', 'PATCH', { status })) {
    toast.add({ color: 'success', title: status === 'published' ? 'الكورس اتنشر — طلابك يقدروا يشوفوه' : status === 'archived' ? 'الكورس اتقفل' : 'الكورس رجع مسودة' })
  }
  busy.value = false
}

const menu = computed<DropdownMenuItem[][]>(() => [[
  ...(course.value?.status !== 'archived' ? [{ label: 'اقفل الكورس', description: 'ما حدش يقدر يشترك فيه تاني', icon: 'i-lucide-archive', onSelect: () => setStatus('archived') }] : [{ label: 'رجّعه مسودة', icon: 'i-lucide-undo-2', onSelect: () => setStatus('draft') }]),
  ...(!course.value?.published_at ? [{ label: 'امسح الكورس', icon: 'i-lucide-trash-2', color: 'error' as const, onSelect: removeCourse }] : []),
]])

async function removeCourse() {
  if (!confirm('تمسح الكورس ده بكل دروسه؟')) return
  try {
    await api(`/courses/${id}`, { method: 'DELETE' })
    await navigateTo('/courses')
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
}

const savingDetails = ref(false)
async function saveDetails() {
  if (!details.value) return
  savingDetails.value = true
  if (await call('', 'PATCH', { title: details.value.title, description: details.value.description || null, price: Math.round((details.value.pounds ?? 0) * 100), access_days: details.value.access_days || null })) {
    toast.add({ color: 'success', title: 'اتحفظ' })
  }
  savingDetails.value = false
}

async function addChapter() {
  const title = prompt('اسم الفصل الجديد', `الفصل ${(course.value?.chapters.length ?? 0) + 1}`)
  if (title?.trim()) await call('/chapters', 'POST', { title: title.trim() })
}
async function renameChapter(chapter: Chapter) {
  const title = prompt('اسم الفصل', chapter.title)
  if (title?.trim() && title !== chapter.title) await call(`/chapters/${chapter.id}`, 'PATCH', { title: title.trim() })
}
async function deleteChapter(chapter: Chapter) {
  if (confirm(`تمسح «${chapter.title}»؟`)) await call(`/chapters/${chapter.id}`, 'DELETE')
}

/** One step up / down through the whole course (crossing into the next chapter at its edge). */
async function move(lesson: Lesson, step: -1 | 1) {
  if (!course.value) return
  const order = course.value.chapters.map(c => ({ id: c.id, lessons: c.lessons.map(l => l.id) }))
  const ci = order.findIndex(c => c.lessons.includes(lesson.id))
  const chapter = order[ci]!
  const li = chapter.lessons.indexOf(lesson.id)
  const target = li + step
  if (target >= 0 && target < chapter.lessons.length) {
    chapter.lessons.splice(li, 1)
    chapter.lessons.splice(target, 0, lesson.id)
  }
  else {
    const next = order[ci + step]
    if (!next) return
    chapter.lessons.splice(li, 1)
    step === 1 ? next.lessons.unshift(lesson.id) : next.lessons.push(lesson.id)
  }
  await call('/order', 'PUT', { chapters: order })
}

async function deleteLesson(lesson: Lesson) {
  if (confirm(`تمسح «${lesson.title}»؟ المشاهدات والامتحانات اللي عليه هتتمسح معاه.`)) await call(`/lessons/${lesson.id}`, 'DELETE')
}

const lessonOpen = ref(false)
const savingLesson = ref(false)
const lessonError = ref<string | null>(null)
const limitViews = ref(false)
const blank = () => ({ id: null as string | null, chapter_id: '', kind: 'video' as LessonKind, title: '', media_id: null as string | null, free_preview: false, requires_previous: false, max_views: 3 as number | null, count_percent: 70, publish_at: '' })
const lessonForm = reactive(blank())

function toLocalInput(iso: string | null): string {
  if (!iso) return ''
  const d = new Date(iso)
  return new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 16)
}

function newLesson(chapterId: string) {
  Object.assign(lessonForm, blank(), { chapter_id: chapterId })
  limitViews.value = false
  lessonError.value = null
  lessonOpen.value = true
}
function editLesson(lesson: Lesson) {
  Object.assign(lessonForm, { ...lesson, max_views: lesson.max_views ?? 3, publish_at: toLocalInput(lesson.publish_at) })
  limitViews.value = lesson.max_views !== null
  lessonError.value = null
  lessonOpen.value = true
}

async function saveLesson() {
  savingLesson.value = true
  lessonError.value = null
  const body: Record<string, unknown> = {
    chapter_id: lessonForm.chapter_id,
    title: lessonForm.title,
    free_preview: lessonForm.free_preview,
    requires_previous: lessonForm.requires_previous,
    max_views: limitViews.value ? lessonForm.max_views : null,
    count_percent: lessonForm.count_percent,
    publish_at: lessonForm.publish_at ? new Date(lessonForm.publish_at).toISOString() : null,
  }
  if (lessonForm.kind !== 'exam') body.media_id = lessonForm.media_id
  if (!lessonForm.id) body.kind = lessonForm.kind
  try {
    fill((await api<{ data: CourseDetail }>(lessonForm.id ? `/courses/${id}/lessons/${lessonForm.id}` : `/courses/${id}/lessons`, { method: lessonForm.id ? 'PATCH' : 'POST', body })).data)
    lessonOpen.value = false
  }
  catch (e) {
    lessonError.value = apiErrorMessage(e)
  }
  finally {
    savingLesson.value = false
  }
}
</script>
