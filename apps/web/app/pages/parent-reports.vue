<template>
  <div class="max-w-4xl space-y-6">
    <PageHeader title="تقارير أولياء الأمور" description="تقرير الأسبوع لكل طالب مشترك عنده رقم ولي أمر: شاف إيه، وفاضله إيه، وآخر امتحان. بيتبعت من واتساب بتاعك، ومعاه لينك فيه التفاصيل.">
      <div class="flex gap-1 rounded-(--ui-radius) bg-(--ui-bg-elevated) p-1">
        <UButton v-for="d in [7, 14, 30]" :key="d" size="sm" :color="days === d ? 'primary' : 'neutral'" :variant="days === d ? 'solid' : 'ghost'" :label="`آخر ${d} يوم`" @click="days = d" />
      </div>
    </PageHeader>

    <div class="flex items-center gap-3 text-sm">
      <span class="text-(--ui-text-muted)">اتبعت <span class="num font-bold">{{ sent.size }}</span> من <span class="num font-bold">{{ rows.length }}</span></span>
      <UButton v-if="next" class="ms-auto" icon="i-lucide-send" :label="`ابعت اللي بعده (${next.student})`" @click="send(next)" />
    </div>

    <p v-if="!rows.length" class="app-card p-8 text-center text-sm text-(--ui-text-muted)">
      مفيش طلاب مشتركين عندهم رقم ولي أمر. اطلب رقم ولي الأمر من إعدادات منصتك.
    </p>
    <UCard v-else :ui="{ body: 'p-0 sm:p-0' }">
      <div class="divide-y divide-(--ui-border)">
        <div v-for="r in rows" :key="r.learner_id" class="flex flex-wrap items-center gap-3 px-4 py-3 text-sm" :class="{ 'opacity-60': sent.has(r.learner_id) }">
          <div class="min-w-0 flex-1">
            <p class="font-bold">
              {{ r.student }}
            </p>
            <p class="text-xs text-(--ui-text-muted)">
              {{ r.message.watched }}<template v-if="r.missing"> · لسه <span class="num">{{ r.missing }}</span></template><template v-if="r.message.exam"> · {{ r.message.exam }}</template>
            </p>
          </div>
          <span class="num text-xs text-(--ui-text-muted)">{{ localPhone(r.parent_phone) }}</span>
          <UButton size="sm" :color="sent.has(r.learner_id) ? 'neutral' : 'success'" :variant="sent.has(r.learner_id) ? 'ghost' : 'soft'" :icon="sent.has(r.learner_id) ? 'i-lucide-check' : 'i-lucide-message-circle'" :label="sent.has(r.learner_id) ? 'اتبعت' : 'ابعت'" @click="send(r)" />
        </div>
      </div>
    </UCard>
  </div>
</template>

<script setup lang="ts">
import type { ParentReportRow } from '~/types/manhagy'

definePageMeta({ permission: 'reports.parents' })

const api = useApi()
const messages = useMessages()
const days = ref(7)
const { data } = await useAsyncData('parent-reports', () => api<{ data: ParentReportRow[] }>('/parent-reports', { query: { days: days.value } }), { watch: [days] })
const rows = computed(() => data.value?.data ?? [])
const sent = ref(new Set<string>())
const next = computed(() => rows.value.find(r => !sent.value.has(r.learner_id)))

function send(r: ParentReportRow) {
  messages.send('parent_report', r.parent_phone, r.message, { type: 'learner', id: r.learner_id })
  sent.value = new Set([...sent.value, r.learner_id])
}
</script>
