<template>
  <div class="max-w-5xl space-y-6">
    <PageHeader title="الحصص اللايف" description="ضيف درس «لايف» لأي كورس بميعاده. وقت الحصة افتح الاستوديو وابدأ من المتصفح أو OBS، والتسجيل بيتحط في الكورس لوحده." />

    <p v-if="rows && !rows.length" class="app-card p-10 text-center text-sm text-(--ui-text-muted)">
      مفيش حصص لايف. من صفحة الكورس دوس «درس جديد» واختار «لايف».
    </p>
    <div class="space-y-2">
      <NuxtLink v-for="r in rows" :key="r.id" :to="`/live/${r.id}`" class="app-card flex flex-wrap items-center gap-3 p-3 transition hover:border-(--ui-primary)">
        <span class="grid size-10 place-items-center rounded-full" :class="r.status === 'live' ? 'bg-(--ui-error) text-white' : 'bg-(--ui-bg-elevated)'">
          <UIcon name="i-lucide-radio" class="size-5" />
        </span>
        <div class="min-w-0 flex-1">
          <p class="truncate font-bold">
            {{ r.title }}
          </p>
          <p class="text-xs text-(--ui-text-muted)">
            {{ r.course_title }} · {{ r.publish_at ? formatDate(r.publish_at, true) : '—' }}
          </p>
        </div>
        <UBadge v-if="r.status === 'live'" color="error" label="لايف دلوقتي" />
        <UBadge v-else-if="r.status === 'ended'" color="success" variant="subtle" :label="r.has_replay ? 'التسجيل في الكورس' : 'خلصت'" />
        <UBadge v-else color="neutral" variant="subtle" label="جاية" />
        <span v-if="r.peak_viewers" class="num text-xs text-(--ui-text-muted)">{{ r.peak_viewers }} أقصى عدد</span>
      </NuxtLink>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { LiveLessonRow } from '~/types/manhagy'

definePageMeta({ permission: 'live.moderate' })

const api = useApi()
const { data: rows } = await useAsyncData('live-lessons', async () => (await api<{ data: LiveLessonRow[] }>('/live')).data)
</script>
