<template>
  <div class="max-w-5xl space-y-6">
    <PageHeader title="الموزّعين" description="المكتبات والسناتر والمساعدين اللي بيبيعوا كروتك: اتسلّموا كام، اتباع كام، وعليهم كام.">
      <UButton icon="i-lucide-plus" label="موزّع جديد" @click="openForm()" />
    </PageHeader>

    <div class="grid gap-3 sm:grid-cols-2">
      <p v-if="!rows?.length" class="app-card p-8 text-center text-sm text-(--ui-text-muted) sm:col-span-2">
        ضيف المكتبات اللي بتوزّع كروتك، وكل دفعة أكواد اربطها بموزّع.
      </p>
      <NuxtLink v-for="d in rows" :key="d.id" :to="`/codes/distributors/${d.id}`" class="app-card space-y-3 p-4 transition hover:ring-1 hover:ring-primary" :class="{ 'opacity-60': !d.active }">
        <div class="flex items-center justify-between">
          <div>
            <p class="font-bold">
              {{ d.name }}
            </p>
            <p class="text-xs text-(--ui-text-muted)">
              {{ KINDS[d.kind] }}<template v-if="d.phone"> · <span class="num">{{ d.phone }}</span></template>
            </p>
          </div>
          <div class="text-end">
            <p class="text-xs text-(--ui-text-muted)">
              عليه
            </p>
            <p class="num text-lg font-extrabold" :class="(d.stats?.due ?? 0) > 0 ? 'text-(--ui-warning)' : ''">
              {{ formatMoney(d.stats?.due ?? 0) }}
            </p>
          </div>
        </div>
        <div class="grid grid-cols-3 gap-2 text-center text-sm">
          <div><p class="num font-bold">{{ d.stats?.given }}</p><p class="text-xs text-(--ui-text-muted)">اتسلّم</p></div>
          <div><p class="num font-bold">{{ d.stats?.used }}</p><p class="text-xs text-(--ui-text-muted)">اتباع</p></div>
          <div><p class="num font-bold">{{ formatMoney(d.stats?.collected ?? 0) }}</p><p class="text-xs text-(--ui-text-muted)">اتحصّل</p></div>
        </div>
      </NuxtLink>
    </div>

    <DistributorForm v-model:open="formOpen" :distributor="editing" @saved="() => refresh()" />
  </div>
</template>

<script setup lang="ts">
import type { Distributor } from '~/types/manhagy'

definePageMeta({ permission: 'codes.manage' })

const KINDS: Record<Distributor['kind'], string> = { library: 'مكتبة', center: 'سنتر', assistant: 'مساعد', other: 'غير كده' }
const api = useApi()
const { data: rows, refresh } = await useAsyncData('distributors', async () => (await api<{ data: Distributor[] }>('/codes/distributors')).data)
const formOpen = ref(false)
const editing = ref<Distributor | null>(null)
function openForm(d: Distributor | null = null) {
  editing.value = d
  formOpen.value = true
}
</script>
