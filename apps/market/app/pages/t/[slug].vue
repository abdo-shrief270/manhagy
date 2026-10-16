<template>
  <div v-if="teacher" class="space-y-8">
    <section class="card flex flex-col gap-5 p-6 sm:flex-row sm:items-center">
      <span class="grid size-20 shrink-0 place-items-center rounded-full text-3xl font-extrabold text-white" :style="{ background: teacherColor(teacher.color) }">{{ teacher.name.slice(0, 1) }}</span>
      <div class="min-w-0 flex-1 space-y-2">
        <h1 class="text-2xl font-extrabold">
          {{ teacher.name }}
        </h1>
        <p v-if="teacher.headline" class="text-muted">
          {{ teacher.headline }}
        </p>
        <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm">
          <StarRating :rating="teacher.rating" :reviews="teacher.reviews" />
          <span class="text-muted"><span class="num">{{ formatNumber(teacher.students) }}</span> طالب</span>
          <span class="text-muted"><span class="num">{{ formatNumber(teacher.courses_count) }}</span> كورس</span>
        </div>
      </div>
      <a :href="teacher.site_url" class="btn">منصة المدرس</a>
    </section>
    <p v-if="teacher.about" class="card whitespace-pre-line p-6 leading-relaxed">
      {{ teacher.about }}
    </p>
    <section class="space-y-3">
      <h2 class="text-xl font-extrabold">
        الكورسات
      </h2>
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <CourseTile v-for="c in teacher.courses" :key="c.id" :course="c" />
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import type { TeacherPage } from '~/types'

const slug = String(useRoute().params.slug)
const { data: teacher } = await useAsyncData(`teacher-${slug}`, () => marketFetch<{ data: TeacherPage }>(`/teachers/${slug}`).then(r => r.data))
if (!teacher.value) {
  throw createError({ statusCode: 404, fatal: true })
}
useSeoMeta({
  title: () => teacher.value?.name ?? '',
  description: () => (teacher.value?.headline ?? teacher.value?.about ?? `كورسات ${teacher.value?.name}`).slice(0, 160),
})
</script>
