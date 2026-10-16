<template>
  <UModal v-model:open="open" :ui="{ content: 'sm:max-w-2xl' }" title="بحث سريع" description="دوّر على صفحة أو طالب">
    <template #content>
      <UCommandPalette
        v-model:search-term="term"
        :groups="groups"
        :loading="loading"
        placeholder="اكتب اسم صفحة، أو اسم طالب أو رقمه…"
        close
        class="h-96"
        @update:open="open = $event"
      >
        <template #empty>
          <p class="py-6 text-center text-sm text-(--ui-text-muted)">
            {{ term.trim().length < 2 ? 'اكتب حرفين على الأقل عشان تدوّر في الطلاب' : 'مفيش نتايج' }}
          </p>
        </template>
      </UCommandPalette>
    </template>
  </UModal>
</template>

<script setup lang="ts">
import type { CommandPaletteGroup, CommandPaletteItem } from '@nuxt/ui'
import type { Learner } from '~/types/manhagy'

/** Ctrl/⌘+K or "/" from anywhere: jump to a page, or find a student. */
const open = defineModel<boolean>('open', { default: false })

const api = useApi()
const store = useSessionStore()
const { allItems } = useNavigation()
const actions = useQuickActions()
const feedback = useFeedback()
const installApp = useInstallApp()

const term = ref('')
const loading = ref(false)
const learners = ref<Learner[]>([])

let timer: ReturnType<typeof setTimeout> | undefined
let requestId = 0

watch(term, (value) => {
  clearTimeout(timer)
  const q = value.trim()
  if (!store.can('learners.view') || q.length < 2) {
    learners.value = []
    loading.value = false
    return
  }
  timer = setTimeout(async () => {
    const id = ++requestId
    loading.value = true
    try {
      const res = await api<{ data: Learner[] }>('/learners', { query: { q } })
      if (id === requestId) {
        learners.value = res.data.slice(0, 8)
      }
    }
    catch {
      if (id === requestId) {
        learners.value = []
      }
    }
    finally {
      if (id === requestId) {
        loading.value = false
      }
    }
  }, 250)
})

watch(open, (isOpen) => {
  if (!isOpen) {
    term.value = ''
  }
})

onBeforeUnmount(() => clearTimeout(timer))

function go(to: string) {
  open.value = false
  navigateTo(to)
}

const groups = computed<CommandPaletteGroup<CommandPaletteItem>[]>(() => {
  const result: CommandPaletteGroup<CommandPaletteItem>[] = [{
    id: 'actions',
    label: 'إجراءات سريعة',
    items: actions.value.map(a => ({
      label: a.label,
      suffix: a.description,
      icon: a.icon,
      kbds: a.kbd ? [a.kbd] : undefined,
      onSelect: () => (a.run ? (open.value = false, a.run()) : a.to && go(a.to)),
    })),
  }, {
    id: 'pages',
    label: 'الصفحات',
    items: allItems.value.map(item => ({ label: item.label, icon: item.icon, onSelect: () => go(item.to) })),
  }, {
    id: 'help',
    label: 'مساعدة',
    items: [{
      label: 'ابعت ملاحظة',
      suffix: 'مشكلة، اقتراح، أو سؤال لفريق منهجي',
      icon: 'i-lucide-message-square-heart',
      onSelect: () => {
        open.value = false
        feedback.show()
      },
    }, ...(installApp.installable.value
      ? [{ label: 'نزّل التطبيق', suffix: 'على الموبايل أو الكمبيوتر', icon: 'i-lucide-download', onSelect: () => { open.value = false; installApp.install() } }]
      : [])],
  }]

  if (learners.value.length) {
    result.push({
      id: 'learners',
      label: 'الطلاب',
      ignoreFilter: true, // already searched by the server (name or phone)
      items: learners.value.map(l => ({
        label: l.name,
        suffix: [localPhone(l.phone), l.grade].filter(Boolean).join(' · '),
        icon: l.blocked ? 'i-lucide-user-x' : 'i-lucide-user',
        onSelect: () => go(`/learners/${l.id}`),
      })),
    })
  }

  return result
})
</script>
