<template>
  <div class="space-y-6">
    <PageHeader title="الأكواد" description="كروت أكواد لكورس أو رصيد. كل كود بيتستخدم مرة واحدة، والكود نفسه بيظهر وقت ما تعمل الدفعة بس.">
      <UButton color="neutral" variant="outline" icon="i-lucide-search-check" label="استعلام عن كود" @click="lookupOpen = true" />
      <UButton v-if="store.can('codes.manage')" icon="i-lucide-ticket-plus" label="دفعة جديدة" to="/codes/new" />
    </PageHeader>

    <UCard :ui="{ body: 'p-0 sm:p-0' }">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-(--ui-bg-elevated) text-(--ui-text-muted)">
            <tr>
              <th class="p-3 text-start font-bold">
                الدفعة
              </th>
              <th class="p-3 text-start font-bold">
                بتفتح
              </th>
              <th class="p-3 text-start font-bold">
                الموزّع
              </th>
              <th class="p-3 text-start font-bold">
                اتستخدم
              </th>
              <th class="p-3 text-start font-bold">
                الكارت بـ
              </th>
              <th class="p-3 text-start font-bold">
                اتعملت
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!batches?.length">
              <td colspan="6" class="p-8 text-center text-(--ui-text-muted)">
                لسه مفيش أكواد.
              </td>
            </tr>
            <tr v-for="b in batches" :key="b.id" class="cursor-pointer border-t border-(--ui-border) hover:bg-(--ui-bg-elevated)/50" :class="{ 'opacity-60': b.status === 'cancelled' }" @click="navigateTo(`/codes/${b.id}`)">
              <td class="p-3">
                <span class="num font-bold">{{ b.number }}</span>
                <span v-if="b.label" class="text-(--ui-text-muted)"> · {{ b.label }}</span>
                <UBadge v-if="b.status === 'cancelled'" class="ms-1" size="sm" color="error" variant="subtle" label="ملغية" />
              </td>
              <td class="p-3">
                {{ b.kind === 'course' ? b.course_title : `رصيد ${formatMoney(b.value)}` }}
              </td>
              <td class="p-3 text-(--ui-text-muted)">
                {{ b.distributor_name ?? '—' }}
              </td>
              <td class="p-3">
                <div class="flex items-center gap-2">
                  <div class="h-2 w-20 overflow-hidden rounded-full bg-(--ui-bg-elevated)">
                    <div class="h-full rounded-full bg-(--app-chart)" :style="{ width: `${Math.round(b.stats.used / b.count * 100)}%` }" />
                  </div>
                  <span class="num">{{ b.stats.used }} / {{ b.count }}</span>
                </div>
              </td>
              <td class="p-3 num">
                {{ formatMoney(b.unit_price) }}
              </td>
              <td class="p-3 num text-(--ui-text-muted)">
                {{ formatDate(b.created_at) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </UCard>

    <UModal v-model:open="lookupOpen" title="استعلام عن كود" description="طالب بيقول الكود مش شغال؟ اكتبه هنا وتعرف حصله إيه.">
      <template #body>
        <form class="space-y-4" @submit.prevent="lookup">
          <UInput v-model="code" dir="ltr" class="w-full" size="xl" placeholder="XXXX-XXXX-XXXX" :ui="{ base: 'num text-center tracking-widest' }" autofocus />
          <UButton type="submit" block :loading="looking" label="دوّر" />
          <div v-if="found" class="rounded-(--ui-radius) bg-(--ui-bg-elevated) p-3 text-sm">
            <p v-if="!found.valid" class="font-bold text-(--ui-error)">
              الكود ده فيه غلط في الكتابة (حرف التحقق مش مظبوط).
            </p>
            <p v-else-if="!found.found" class="font-bold">
              الكود مكتوب صح بس مش من أكوادك.
            </p>
            <div v-else class="space-y-1">
              <p>دفعة <NuxtLink :to="`/codes/${found.batch_id}`" class="num font-bold text-primary">{{ found.batch }}</NuxtLink> · كارت رقم <span class="num font-bold">{{ found.serial }}</span></p>
              <p v-if="found.status === 'used'">
                اتستخدم {{ formatDate(found.used_at, true) }} بواسطة <b>{{ found.learner_name }}</b>
              </p>
              <p v-else-if="found.status === 'cancelled'" class="text-(--ui-error) font-bold">
                الكود ده اتلغى.
              </p>
              <p v-else-if="found.expired" class="text-(--ui-warning) font-bold">
                الكود انتهت صلاحيته.
              </p>
              <p v-else class="text-(--ui-success) font-bold">
                الكود سليم ولسه ما اتستخدمش.
              </p>
            </div>
          </div>
        </form>
      </template>
    </UModal>
  </div>
</template>

<script setup lang="ts">
import type { CodeBatch } from '~/types/manhagy'

definePageMeta({ permission: 'codes.view' })

const api = useApi()
const store = useSessionStore()
const route = useRoute()
const { data: batches } = await useAsyncData('code-batches', async () => (await api<{ data: CodeBatch[] }>('/codes/batches')).data)

const lookupOpen = ref(route.query.lookup === '1')
const code = ref('')
const looking = ref(false)
const found = ref<{ valid: boolean, found?: boolean, batch?: string, batch_id?: string, serial?: number, status?: string, expired?: boolean, used_at?: string | null, learner_name?: string | null } | null>(null)
async function lookup() {
  looking.value = true
  found.value = (await api<{ data: NonNullable<typeof found.value> }>('/codes/lookup', { query: { code: code.value } }).catch(() => ({ data: null }))).data
  looking.value = false
}
</script>
