<template>
  <div class="max-w-5xl space-y-6">
    <PageHeader title="المجموعات" description="كل مجموعة بمواعيدها كل أسبوع، والحصص بتتعمل لوحدها للأسبوع الجاي.">
      <UButton v-if="store.can('center.manage')" icon="i-lucide-plus" label="مجموعة جديدة" @click="formOpen = true" />
    </PageHeader>
    <p v-if="!groups?.length" class="app-card p-8 text-center text-sm text-(--ui-text-muted)">
      لسه مفيش مجموعات.
    </p>
    <div class="grid gap-3 sm:grid-cols-2">
      <NuxtLink v-for="g in groups" :key="g.id" :to="`/center/groups/${g.id}`" class="app-card space-y-2 p-4 transition hover:ring-1 hover:ring-primary" :class="{ 'opacity-60': !g.active }">
        <div class="flex items-start justify-between gap-2">
          <p class="font-bold">
            {{ g.name }}
          </p>
          <UBadge color="neutral" variant="subtle" class="num" :label="`${g.members}${g.capacity ? ` / ${g.capacity}` : ''} طالب`" />
        </div>
        <p class="text-sm text-(--ui-text-muted)">
          {{ g.schedule_label }}<template v-if="g.room">
            · {{ g.room }}
          </template>
        </p>
        <p class="text-sm">
          <span v-if="g.teacher">{{ g.teacher }} · </span>الحصة <span class="num font-bold">{{ formatMoney(g.session_fee) }}</span><template v-if="g.monthly_fee">
            · الشهر <span class="num font-bold">{{ formatMoney(g.monthly_fee) }}</span>
          </template>
        </p>
      </NuxtLink>
    </div>
    <GroupForm v-model:open="formOpen" :group="null" @saved="g => navigateTo(`/center/groups/${g.id}`)" />
  </div>
</template>

<script setup lang="ts">
import type { CenterGroup } from '~/types/manhagy'

definePageMeta({ module: 'center', permission: 'center.door' })

const api = useApi()
const store = useSessionStore()
const formOpen = ref(false)
const { data: groups } = await useAsyncData('center-groups', async () => (await api<{ data: CenterGroup[] }>('/center/groups')).data)
</script>
