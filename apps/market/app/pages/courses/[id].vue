<template>
  <div v-if="course" class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
      <div class="space-y-3">
        <p class="text-sm text-muted">
          {{ course.subject }}
        </p>
        <h1 class="text-3xl font-extrabold leading-tight">
          {{ course.title }}
        </h1>
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
          <StarRating :rating="course.rating" :reviews="course.reviews" />
          <span class="text-muted"><span class="num">{{ formatNumber(course.students) }}</span> طالب</span>
          <span class="text-muted"><span class="num">{{ formatNumber(course.lessons_count) }}</span> درس</span>
          <span v-if="totalTime" class="text-muted">{{ totalTime }} فيديو</span>
        </div>
        <p v-if="course.description" class="whitespace-pre-line leading-relaxed">
          {{ course.description }}
        </p>
      </div>

      <section class="space-y-3">
        <h2 class="text-xl font-extrabold">
          محتوى الكورس
        </h2>
        <details v-for="(chapter, i) in course.syllabus" :key="i" class="card group" :open="i === 0">
          <summary class="flex cursor-pointer list-none items-center justify-between gap-2 p-4 font-bold">
            {{ chapter.title }}
            <span class="num text-sm font-normal text-muted">{{ chapter.lessons.length }} درس</span>
          </summary>
          <ul class="divide-y divide-line border-t border-line">
            <li v-for="(lesson, j) in chapter.lessons" :key="j" class="flex items-center gap-3 px-4 py-2.5 text-sm">
              <span class="w-14 shrink-0 text-xs text-muted">{{ LESSON_KIND[lesson.kind] }}</span>
              <span class="flex-1">{{ lesson.title }}</span>
              <span v-if="lesson.free_preview" class="rounded-full bg-ok/10 px-2 py-0.5 text-xs font-bold text-ok">مجاني</span>
              <span v-if="lesson.duration_seconds" class="num text-xs text-muted">{{ duration(lesson.duration_seconds) }}</span>
            </li>
          </ul>
        </details>
      </section>

      <section class="space-y-3">
        <h2 class="text-xl font-extrabold">
          رأي الطلاب
        </h2>
        <div v-if="course.reviews" class="card flex flex-wrap items-center gap-6 p-4">
          <div class="text-center">
            <p class="num text-4xl font-extrabold">
              {{ course.rating?.toFixed(1) }}
            </p>
            <p class="text-sm text-muted">
              <span class="num">{{ formatNumber(course.reviews) }}</span> تقييم
            </p>
          </div>
          <div class="min-w-48 flex-1 space-y-1">
            <div v-for="star in [5, 4, 3, 2, 1]" :key="star" class="flex items-center gap-2 text-sm">
              <span class="num w-3">{{ star }}</span>
              <span class="text-accent" aria-hidden="true">★</span>
              <span class="h-2 flex-1 overflow-hidden rounded-full bg-line"><span class="block h-full rounded-full bg-accent" :style="{ width: `${share(star)}%` }" /></span>
              <span class="num w-8 text-end text-muted">{{ course.distribution[star] ?? 0 }}</span>
            </div>
          </div>
        </div>
        <p v-else class="card p-6 text-center text-muted">
          لسه مفيش تقييمات — الطلاب المشتركين بيقيّموا من منصة المدرس.
        </p>
        <div v-for="r in course.reviews_list" :key="r.id" class="card space-y-1 p-4">
          <p class="flex items-center gap-2 text-sm">
            <span class="font-bold">{{ r.first_name }}</span>
            <span class="text-accent" :aria-label="`${r.rating} من 5`">{{ '★'.repeat(r.rating) }}</span>
            <span class="text-muted">{{ formatDate(r.at) }}</span>
          </p>
          <p class="leading-relaxed">
            {{ r.comment }}
          </p>
        </div>
      </section>

      <section v-if="course.more_from_teacher.length" class="space-y-3">
        <h2 class="text-xl font-extrabold">
          كورسات تانية لـ {{ course.teacher.name }}
        </h2>
        <div class="grid gap-4 sm:grid-cols-2">
          <CourseTile v-for="c in course.more_from_teacher" :key="c.id" :course="c" />
        </div>
      </section>
    </div>

    <aside class="space-y-4 lg:sticky lg:top-20 lg:self-start">
      <div class="card space-y-4 p-5">
        <p class="num text-3xl font-extrabold" :class="course.price ? '' : 'text-ok'">
          {{ formatMoney(course.price) }}
        </p>
        <p class="text-sm text-muted">
          الاشتراك: {{ accessText(course.access_days) }}
        </p>
        <a :href="course.url" class="btn w-full text-lg">اشترك من منصة المدرس</a>
        <p v-if="course.free_previews" class="text-center text-sm text-ok">
          فيه <span class="num">{{ course.free_previews }}</span> درس تتفرج عليهم ببلاش بعد التسجيل
        </p>
        <p class="text-xs leading-relaxed text-muted">
          الكورس على منصة المدرس نفسه: هتسجّل برقم موبايلك، وتدفع بالكود أو من المحفظة زي ما المدرس محدد.
        </p>
      </div>
      <NuxtLink :to="`/t/${course.teacher.slug}`" class="card flex items-center gap-3 p-4 text-ink hover:border-brand hover:text-ink">
        <span class="grid size-12 shrink-0 place-items-center rounded-full text-lg font-extrabold text-white" :style="{ background: teacherColor(course.teacher.color) }">{{ course.teacher.name.slice(0, 1) }}</span>
        <span class="min-w-0">
          <span class="block font-bold">{{ course.teacher.name }}</span>
          <span v-if="course.teacher.headline" class="block truncate text-sm text-muted">{{ course.teacher.headline }}</span>
        </span>
      </NuxtLink>
    </aside>
  </div>
</template>

<script setup lang="ts">
import type { CourseDetail } from '~/types'

const LESSON_KIND = { video: 'فيديو', pdf: 'ملزمة', exam: 'امتحان' } as const
const id = String(useRoute().params.id)
const { data: course } = await useAsyncData(`course-${id}`, () => marketFetch<{ data: CourseDetail }>(`/courses/${id}`).then(r => r.data))
if (!course.value) {
  throw createError({ statusCode: 404, fatal: true })
}

const totalTime = computed(() => {
  const seconds = course.value?.syllabus.flatMap(c => c.lessons).reduce((sum, l) => sum + (l.duration_seconds ?? 0), 0) ?? 0
  return seconds ? duration(seconds) : ''
})

function share(star: number) {
  const total = course.value?.reviews ?? 0
  return total ? Math.round(((course.value?.distribution[star] ?? 0) / total) * 100) : 0
}

const base = String(useRuntimeConfig().public.marketUrl).replace(/\/$/, '')
useSeoMeta({
  title: () => `${course.value?.title} — ${course.value?.teacher.name}`,
  description: () => (course.value?.description ?? `${course.value?.title}: ${course.value?.subject}`).slice(0, 160),
  ogTitle: () => course.value?.title,
})
useHead(() => ({
  script: course.value
    ? [{
        type: 'application/ld+json',
        innerHTML: JSON.stringify({
          '@context': 'https://schema.org',
          '@type': 'Course',
          'name': course.value.title,
          'description': (course.value.description ?? course.value.subject).slice(0, 500),
          'url': `${base}/courses/${course.value.id}`,
          'inLanguage': 'ar',
          'provider': { '@type': 'Person', 'name': course.value.teacher.name, 'url': `${base}/t/${course.value.teacher.slug}` },
          'offers': { '@type': 'Offer', 'price': (course.value.price / 100).toFixed(2), 'priceCurrency': 'EGP', 'category': course.value.price ? 'Paid' : 'Free', 'url': course.value.url },
          'hasCourseInstance': { '@type': 'CourseInstance', 'courseMode': 'online' },
          ...(course.value.rating !== null && course.value.reviews ? { aggregateRating: { '@type': 'AggregateRating', 'ratingValue': course.value.rating, 'reviewCount': course.value.reviews, 'bestRating': 5 } } : {}),
        }),
      }]
    : [],
}))
</script>
