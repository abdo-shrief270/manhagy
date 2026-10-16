<template>
  <div class="space-y-6">
    <PageHeader title="الطلاب" description="اللي سجّلوا على منصتك. كل طالب حسابه برقم موبايله، وبيشوف اللي اشترك فيه بس." />

    <div class="flex flex-wrap items-center gap-2">
      <UInput v-model="q" icon="i-lucide-search" placeholder="اسم أو رقم موبايل" class="w-full sm:w-72" />
      <div class="flex gap-1 rounded-(--ui-radius) bg-(--ui-bg-elevated) p-1">
        <UButton size="sm" :color="blocked === null ? 'primary' : 'neutral'" :variant="blocked === null ? 'solid' : 'ghost'" label="الكل" @click="blocked = null" />
        <UButton size="sm" :color="blocked === true ? 'primary' : 'neutral'" :variant="blocked === true ? 'solid' : 'ghost'" label="الموقوفين" @click="blocked = true" />
      </div>
      <span v-if="meta" class="ms-auto text-sm text-(--ui-text-muted)"><span class="num font-bold">{{ meta.total }}</span> طالب</span>
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
                الموبايل
              </th>
              <th class="p-3 text-start font-bold">
                الصف
              </th>
              <th class="p-3 text-start font-bold">
                الأجهزة
              </th>
              <th class="p-3 text-start font-bold">
                آخر ظهور
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows.length">
              <td colspan="5" class="p-8 text-center text-(--ui-text-muted)">
                {{ q ? 'مفيش طالب بالاسم أو الرقم ده.' : 'لسه محدش سجّل. ابعت لينك منصتك لطلابك.' }}
              </td>
            </tr>
            <tr v-for="l in rows" :key="l.id" class="cursor-pointer border-t border-(--ui-border) hover:bg-(--ui-bg-elevated)/50" @click="navigateTo(`/learners/${l.id}`)">
              <td class="p-3">
                <div class="flex items-center gap-2">
                  <UAvatar :alt="l.name" size="sm" />
                  <span class="font-bold">{{ l.name }}</span>
                  <UBadge v-if="l.blocked" size="sm" color="error" variant="subtle" label="موقوف" />
                </div>
              </td>
              <td class="p-3 num">
                {{ localPhone(l.phone) }}
              </td>
              <td class="p-3 max-w-56 truncate text-(--ui-text-muted)">
                {{ l.grade ?? '—' }}
              </td>
              <td class="p-3 num">
                {{ l.devices }}
              </td>
              <td class="p-3 text-(--ui-text-muted)">
                {{ l.last_seen_at ? timeAgo(l.last_seen_at) : '—' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </UCard>

    <div v-if="meta && meta.last_page > 1" class="flex justify-center">
      <UPagination v-model:page="page" :total="meta.total" :items-per-page="50" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { refDebounced } from '@vueuse/core'
import type { Learner } from '~/types/manhagy'

definePageMeta({ permission: 'learners.view' })

const api = useApi()
const q = ref('')
const debounced = refDebounced(q, 300)
const blocked = ref<boolean | null>(null)
const page = ref(1)
watch([debounced, blocked], () => (page.value = 1))

const { data } = await useAsyncData('learners', () => api<{ data: Learner[], meta: { total: number, page: number, last_page: number } }>('/learners', {
  query: { q: debounced.value || undefined, blocked: blocked.value === null ? undefined : 1, page: page.value },
}), { watch: [debounced, blocked, page] })
const rows = computed(() => data.value?.data ?? [])
const meta = computed(() => data.value?.meta)
</script>
