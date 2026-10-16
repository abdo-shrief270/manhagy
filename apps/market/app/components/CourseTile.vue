<template>
  <NuxtLink :to="`/courses/${course.id}`" class="card group flex flex-col overflow-hidden text-ink transition hover:-translate-y-0.5 hover:border-brand hover:text-ink hover:shadow-sm">
    <div class="h-2" :style="{ background: teacherColor(course.teacher.color) }" />
    <div class="flex flex-1 flex-col gap-2 p-4">
      <p class="line-clamp-1 text-xs text-muted">
        {{ shortSubject(course.subject) }}
      </p>
      <h3 class="line-clamp-2 text-base font-bold leading-snug group-hover:text-brand">
        {{ course.title }}
      </h3>
      <p class="text-sm text-muted">
        {{ course.teacher.name }}
      </p>
      <div class="mt-auto flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
        <StarRating :rating="course.rating" :reviews="course.reviews" />
        <span class="text-muted"><span class="num">{{ formatNumber(course.lessons_count) }}</span> درس</span>
        <span v-if="course.students" class="text-muted"><span class="num">{{ formatNumber(course.students) }}</span> طالب</span>
      </div>
      <p class="num text-lg font-extrabold" :class="course.price ? 'text-ink' : 'text-ok'">
        {{ formatMoney(course.price) }}
      </p>
    </div>
  </NuxtLink>
</template>

<script setup lang="ts">
import type { CourseCard } from '~/types'

defineProps<{ course: CourseCard }>()
</script>
