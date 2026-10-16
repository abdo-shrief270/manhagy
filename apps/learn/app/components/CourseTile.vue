<template>
  <NuxtLink :to="`/courses/${course.id}`" class="app-card flex flex-col gap-2 p-4 transition hover:ring-1 hover:ring-primary">
    <div class="flex items-start justify-between gap-2">
      <h3 class="font-bold leading-snug">
        {{ course.title }}
      </h3>
      <UBadge v-if="course.enrolled" color="success" variant="subtle" label="مشترك" />
    </div>
    <p v-if="course.grade" class="truncate text-xs text-(--ui-text-muted)">
      {{ course.grade }}
    </p>
    <div v-if="course.enrolled && course.lessons_count" class="space-y-1">
      <UProgress :model-value="Math.round((course.completed_lessons ?? 0) / course.lessons_count * 100)" size="sm" />
      <p class="text-xs text-(--ui-text-muted)">
        خلّصت <span class="num">{{ course.completed_lessons ?? 0 }}</span> من <span class="num">{{ course.lessons_count }}</span>
      </p>
    </div>
    <div class="mt-auto flex items-center justify-between text-sm">
      <span class="text-(--ui-text-muted)"><span class="num">{{ course.lessons_count }}</span> درس<template v-if="course.free_previews"> · <span class="num">{{ course.free_previews }}</span> مجاني</template></span>
      <span v-if="!course.enrolled" class="num font-extrabold text-primary">{{ course.price ? formatMoney(course.price) : 'مجاني' }}</span>
      <span v-else-if="course.ends_at" class="text-xs text-(--ui-text-muted)">لحد {{ formatDate(course.ends_at) }}</span>
    </div>
  </NuxtLink>
</template>

<script setup lang="ts">
import type { CourseCard } from '~/utils/types'

defineProps<{ course: CourseCard }>()
</script>
