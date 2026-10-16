<template>
  <div class="max-w-5xl space-y-6">
    <PageHeader :title="`المشتركين في «${title}»`" description="مين مشترك، وجه منين، والاشتراك بيخلص إمتى.">
      <UButton color="neutral" variant="outline" icon="i-lucide-arrow-right" label="الكورس" :to="`/courses/${id}`" />
    </PageHeader>

    <div class="flex gap-1 rounded-(--ui-radius) bg-(--ui-bg-elevated) p-1 w-fit">
      <UButton size="sm" :color="active ? 'primary' : 'neutral'" :variant="active ? 'solid' : 'ghost'" label="الساريين" @click="active = true" />
      <UButton size="sm" :color="!active ? 'primary' : 'neutral'" :variant="!active ? 'solid' : 'ghost'" label="الكل" @click="active = false" />
    </div>

    <UCard :ui="{ body: 'p-0 sm:p-0' }">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-(--ui-bg-elevated) text-(--ui-text-muted)">
            <tr>
              <th class="p-3 text-start font-bold">
                الطالب
              </th>
              <th class="p-3 text-start font-bold">
                جه
              </th>
              <th class="p-3 text-start font-bold">
                من
              </th>
              <th class="p-3 text-start font-bold">
                لحد
              </th>
              <th class="p-3 text-start font-bold">
                الحالة
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows?.length">
              <td colspan="5" class="p-6 text-center text-(--ui-text-muted)">
                لسه مفيش مشتركين.
              </td>
            </tr>
            <tr v-for="e in rows" :key="e.id" class="border-t border-(--ui-border)">
              <td class="p-3 font-bold">
                {{ e.learner_name }}
              </td>
              <td class="p-3">
                {{ ENROLLMENT_SOURCES[e.source] }}<span v-if="e.granted_by_name" class="text-(--ui-text-muted)"> · {{ e.granted_by_name }}</span>
              </td>
              <td class="p-3 num">
                {{ formatDate(e.starts_at) }}
              </td>
              <td class="p-3 num">
                {{ e.ends_at ? formatDate(e.ends_at) : '—' }}
              </td>
              <td class="p-3">
                <UBadge v-if="e.revoked_at" color="error" variant="subtle" :label="`اتلغى: ${e.revoked_reason}`" />
                <UBadge v-else :color="e.active ? 'success' : 'neutral'" variant="subtle" :label="e.active ? 'ساري' : 'خلص'" />
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </UCard>
  </div>
</template>

<script setup lang="ts">
import type { CourseSummary, Enrollment } from '~/types/manhagy'

definePageMeta({ permission: 'courses.view' })

const api = useApi()
const id = String(useRoute().params.id)
const active = ref(true)
const { data: course } = await useAsyncData(`course-${id}`, async () => (await api<{ data: CourseSummary }>(`/courses/${id}`)).data)
const title = computed(() => course.value?.title ?? '')
const { data: rows } = await useAsyncData(`enrollments-${id}`, async () => (await api<{ data: Enrollment[] }>('/enrollments', { query: { course_id: id, active: active.value ? 1 : undefined } })).data, { watch: [active] })
</script>
