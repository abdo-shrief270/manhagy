<template>
  <div class="space-y-3">
    <UInput v-model="term" icon="i-lucide-search" class="w-full" placeholder="دوّر: فيزياء تالتة ثانوي، أو اسم مادة الكلية…" />

    <div v-if="term.trim().length >= 2" class="max-h-72 space-y-1 overflow-y-auto">
      <p v-if="!loading && !found.length" class="py-4 text-center text-sm text-(--ui-text-muted)">
        مفيش نتايج. جرّب كلمة تانية أو تصفّح من تحت.
      </p>
      <button
        v-for="n in found"
        :key="n.id"
        type="button"
        class="block w-full rounded-(--ui-radius) p-2 text-start transition hover:bg-(--ui-bg-elevated)"
        :class="{ 'opacity-50': !n.teachable }"
        :disabled="!n.teachable"
        @click="pick(n)"
      >
        <span class="block font-bold">{{ n.name }}</span>
        <span class="block text-xs text-(--ui-text-muted)">{{ n.label }}</span>
      </button>
    </div>

    <div v-else class="space-y-2">
      <div class="flex flex-wrap items-center gap-1 text-sm">
        <UButton size="xs" color="neutral" variant="ghost" label="المناهج" @click="open(null)" />
        <template v-for="p in path" :key="p.id">
          <UIcon name="i-lucide-chevron-left" class="size-3 text-(--ui-text-muted)" />
          <UButton size="xs" color="neutral" variant="ghost" :label="p.name" @click="open(p)" />
        </template>
      </div>
      <div class="grid max-h-72 gap-1 overflow-y-auto sm:grid-cols-2">
        <button
          v-for="n in children"
          :key="n.id"
          type="button"
          class="flex items-center justify-between gap-2 rounded-(--ui-radius) border border-(--ui-border) p-2 text-start transition hover:border-(--ui-border-accented)"
          @click="n.teachable && !n.has_children ? pick(n) : open(n)"
        >
          <span class="min-w-0">
            <span class="block truncate font-bold">{{ n.name }}</span>
            <span class="block text-xs text-(--ui-text-muted)">{{ n.kind_label }}</span>
          </span>
          <span class="flex shrink-0 items-center gap-1">
            <UButton v-if="n.teachable && n.has_children" size="xs" variant="soft" label="اختار" @click.stop="pick(n)" />
            <UIcon v-if="n.has_children" name="i-lucide-chevron-left" class="size-4 text-(--ui-text-muted)" />
          </span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { CurriculumNode } from '~/types/manhagy'

/** Picks a teachable node of the platform's curriculum tree (subject / unit / university course). */
const emit = defineEmits<{ pick: [node: CurriculumNode & { label: string }] }>()

const api = useApi()
const term = ref('')
const loading = ref(false)
const found = ref<(CurriculumNode & { label: string })[]>([])
const children = ref<CurriculumNode[]>([])
const path = ref<CurriculumNode[]>([])

let timer: ReturnType<typeof setTimeout> | undefined
watch(term, (value) => {
  clearTimeout(timer)
  if (value.trim().length < 2) {
    found.value = []
    return
  }
  timer = setTimeout(async () => {
    loading.value = true
    found.value = await api<{ data: (CurriculumNode & { label: string })[] }>('/public/curriculum/search', { query: { q: value.trim() } }).then(r => r.data).catch(() => [])
    loading.value = false
  }, 250)
})

async function open(node: CurriculumNode | null) {
  if (node === null) {
    path.value = []
    children.value = (await api<{ data: CurriculumNode[] }>('/public/curriculum')).data
    return
  }
  const res = (await api<{ data: CurriculumNode & { path: CurriculumNode[], children: CurriculumNode[] } }>(`/public/curriculum/${node.id}`)).data
  path.value = res.path
  children.value = res.children
}
open(null)

function pick(node: CurriculumNode) {
  const label = 'label' in node && node.label ? String(node.label) : [...path.value.map(p => p.name), node.name].filter((v, i, a) => a.indexOf(v) === i).join(' ← ')
  emit('pick', { ...node, label })
}
</script>
