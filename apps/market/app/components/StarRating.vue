<template>
  <span class="inline-flex items-center gap-1" :aria-label="rating === null ? 'لسه مفيش تقييم' : `${rating.toFixed(1)} من 5`">
    <span class="text-accent" aria-hidden="true">★</span>
    <span v-if="rating !== null" class="num font-bold">{{ rating.toFixed(1) }}</span>
    <span v-if="rating !== null && reviews" class="num text-muted">({{ formatNumber(reviews) }})</span>
    <span v-if="rating === null" class="text-muted">جديد</span>
  </span>
</template>

<script setup lang="ts">
defineProps<{ rating: number | null, reviews?: number }>()
</script>
