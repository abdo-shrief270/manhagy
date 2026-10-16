<template>
  <div class="grid gap-2 sm:grid-cols-2">
    <USelect v-model="stage" :items="stages.map(s => ({ value: s.id, label: s.name }))" placeholder="المرحلة" class="w-full" />
    <USelect v-model="grade" :items="grades.map(g => ({ value: g.id, label: g.name }))" :disabled="!stage" placeholder="الصف / السنة" class="w-full" />
  </div>
</template>

<script setup lang="ts">
/** Stage → grade from the platform's curriculum tree. */
const model = defineModel<string | null>({ default: null })
type Node = { id: string, name: string, kind: string, has_children: boolean }

const api = useLearnApi()
const stages = ref<Node[]>([])
const grades = ref<Node[]>([])
const stage = ref<string | undefined>()
const grade = ref<string | undefined>()

onMounted(async () => {
  stages.value = (await api<{ data: Node[] }>('/public/curriculum')).data
})
watch(stage, async (id) => {
  grade.value = undefined
  model.value = null
  grades.value = id ? (await api<{ data: Node[] }>('/public/curriculum', { query: { parent: id } })).data : []
})
watch(grade, (id) => {
  model.value = id ?? null
})
</script>
