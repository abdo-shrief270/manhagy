<template>
  <div class="max-w-5xl space-y-6">
    <USkeleton v-if="!group" class="h-64" />
    <template v-else>
      <PageHeader :title="group.name" :description="[group.schedule_label, group.room, group.teacher].filter(Boolean).join(' · ')">
        <UButton color="neutral" variant="outline" icon="i-lucide-arrow-right" label="المجموعات" to="/center/groups" />
        <UButton v-if="store.can('center.manage')" color="neutral" variant="outline" icon="i-lucide-pencil" label="تعديل" @click="formOpen = true" />
        <UButton v-if="store.can('center.manage') && members.length" color="neutral" variant="outline" icon="i-lucide-printer" label="اطبع الكارنيهات" :loading="loadingCards" @click="printCards" />
        <UButton icon="i-lucide-scan-qr-code" label="افتح الحصة على الباب" :to="`/center/door?group=${group.id}`" />
      </PageHeader>

      <div class="grid gap-4 lg:grid-cols-3">
        <UCard class="lg:col-span-2" :ui="{ body: 'p-0 sm:p-0' }">
          <template #header>
            <div class="flex flex-wrap items-center justify-between gap-2">
              <h2 class="font-bold">
                الطلاب (<span class="num">{{ members.length }}</span><template v-if="group.capacity"> / <span class="num">{{ group.capacity }}</span></template>)
              </h2>
              <div v-if="store.can('center.manage')" class="flex items-center gap-2">
                <USelectMenu v-model="picked" v-model:search-term="term" :items="found" label-key="label" value-key="learner_id" :ignore-filter="true" placeholder="ضيف طالب (اسم أو موبايل)" class="w-64" @update:model-value="add" />
              </div>
            </div>
          </template>
          <p v-if="!members.length" class="p-6 text-center text-sm text-(--ui-text-muted)">
            لسه مفيش طلاب. الطالب لازم يكون مسجّل على منصتك الأول.
          </p>
          <div v-for="m in members" :key="m.id" class="flex items-center gap-3 border-b border-(--ui-border) px-4 py-2.5 text-sm last:border-0">
            <span class="flex-1 font-bold">{{ m.name }}</span>
            <span class="num text-(--ui-text-muted)">{{ localPhone(m.phone) }}</span>
            <UBadge size="sm" color="neutral" variant="subtle" :label="m.plan === 'monthly' ? 'بالشهر' : 'بالحصة'" />
            <UButton v-if="store.can('center.manage')" size="xs" color="error" variant="ghost" icon="i-lucide-user-minus" aria-label="شيله" @click="remove(m.id)" />
          </div>
        </UCard>
        <UCard :ui="{ body: 'p-0 sm:p-0' }">
          <template #header>
            <h2 class="font-bold">
              الحصص الجاية
            </h2>
          </template>
          <NuxtLink v-for="s in group.sessions ?? []" :key="s.id" :to="`/center/door?session=${s.id}`" class="flex items-center justify-between border-b border-(--ui-border) px-4 py-2.5 text-sm last:border-0 hover:bg-(--ui-bg-elevated)/50" :class="{ 'line-through opacity-60': s.status === 'cancelled' }">
            <span class="num">{{ formatDate(s.starts_at, true) }}</span>
            <span v-if="s.present" class="text-(--ui-text-muted)"><span class="num">{{ s.present }}</span> حضروا</span>
          </NuxtLink>
        </UCard>
      </div>
    </template>
    <GroupForm v-if="group" v-model:open="formOpen" :group="group" @saved="reload" />
    <CenterCards v-if="printing" :cards="cards" :center="store.session?.tenant.name ?? ''" />
  </div>
</template>

<script setup lang="ts">
import type { CenterGroup, Learner } from '~/types/manhagy'

definePageMeta({ module: 'center', permission: 'center.door' })

type GroupDetail = CenterGroup & { members: { id: string, learner_id: string, name: string, phone: string | null, plan: 'session' | 'monthly', joined_at: string }[] }

const api = useApi()
const toast = useToast()
const store = useSessionStore()
const id = String(useRoute().params.id)
const group = ref<GroupDetail | null>(null)
const members = computed(() => group.value?.members ?? [])
async function reload() {
  group.value = (await api<{ data: GroupDetail }>(`/center/groups/${id}`)).data
}
onMounted(reload)
const formOpen = ref(false)

const term = ref('')
const picked = ref<string | undefined>()
const found = ref<{ learner_id: string, label: string }[]>([])
let timer: ReturnType<typeof setTimeout> | undefined
watch(term, (q) => {
  clearTimeout(timer)
  if (q.trim().length < 2) return
  timer = setTimeout(async () => {
    const res = await api<{ data: Learner[] }>('/learners', { query: { q } }).catch(() => ({ data: [] }))
    found.value = res.data.map(l => ({ learner_id: l.learner_id, label: `${l.name} · ${localPhone(l.phone)}` }))
  }, 250)
})
async function add(learnerId: string | undefined) {
  if (!learnerId) return
  try {
    group.value = (await api<{ data: GroupDetail }>(`/center/groups/${id}/members`, { method: 'POST', body: { learner_id: learnerId } })).data
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  picked.value = undefined
  term.value = ''
}
async function remove(member: string) {
  if (!confirm('تشيل الطالب من المجموعة؟')) return
  group.value = (await api<{ data: GroupDetail }>(`/center/groups/${id}/members/${member}`, { method: 'DELETE' })).data
}

const { printing, print } = usePrint()
const cards = ref<{ learner_id: string, name: string, phone: string | null, group: string, token: string }[]>([])
const loadingCards = ref(false)
async function printCards() {
  loadingCards.value = true
  cards.value = (await api<{ data: typeof cards.value }>('/center/cards', { query: { group_id: id } })).data
  loadingCards.value = false
  await print()
}
</script>
