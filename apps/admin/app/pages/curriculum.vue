<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h1 class="text-2xl font-extrabold">
          شجرة المناهج
        </h1>
        <p class="text-sm text-(--ui-text-muted)">
          المراحل والصفوف والمواد والجامعات. المدرسين بيربطوا كورساتهم بيها، وبيقترحوا اللي ناقص.
        </p>
      </div>
      <UButton v-if="!reviewing && childKinds.length" icon="i-lucide-plus" label="ضيف هنا" @click="edit(null)" />
    </div>

    <div class="flex flex-wrap items-center gap-2">
      <div class="flex gap-1 rounded-(--ui-radius) bg-(--ui-bg-elevated) p-1">
        <UButton size="sm" :color="!reviewing ? 'primary' : 'neutral'" :variant="!reviewing ? 'solid' : 'ghost'" label="الشجرة" @click="reviewing = false" />
        <UButton size="sm" :color="reviewing ? 'primary' : 'neutral'" :variant="reviewing ? 'solid' : 'ghost'" @click="reviewing = true">
          اقتراحات المدرسين
          <UBadge v-if="data?.meta.review" size="sm" color="warning" class="num" :label="String(data.meta.review)" />
        </UButton>
      </div>
      <UInput v-model="q" icon="i-lucide-search" placeholder="دوّر في الشجرة كلها…" class="w-full sm:w-72" />
    </div>

    <div v-if="!reviewing && !q" class="flex flex-wrap items-center gap-1 text-sm">
      <UButton size="xs" color="neutral" variant="ghost" label="الجذر" @click="go(null)" />
      <template v-for="p in path" :key="p.id">
        <UIcon name="i-lucide-chevron-left" class="size-3 text-(--ui-text-muted)" />
        <UButton size="xs" color="neutral" variant="ghost" :label="p.name" @click="go(p)" />
      </template>
    </div>

    <UCard :ui="{ body: 'p-0 sm:p-0' }">
      <p v-if="!rows.length" class="p-8 text-center text-sm text-(--ui-text-muted)">
        {{ reviewing ? 'مفيش اقتراحات مستنية.' : 'فاضي.' }}
      </p>
      <div v-for="n in rows" :key="n.id" class="flex flex-wrap items-center gap-3 border-b border-(--ui-border) px-4 py-3 text-sm last:border-0">
        <button type="button" class="min-w-0 flex-1 text-start" :disabled="!n.has_children && reviewing" @click="!reviewing && !q ? go(n) : undefined">
          <span class="block font-bold">{{ n.name }} <span v-if="n.code" class="num text-xs text-(--ui-text-muted)">{{ n.code }}</span></span>
          <span class="block text-xs text-(--ui-text-muted)">{{ n.kind_label }}<template v-if="(reviewing || q) && n.label"> · {{ n.label }}</template><template v-if="n.suggested_by"> · اقتراح {{ n.suggested_by }}</template></span>
        </button>
        <UBadge v-if="n.status !== 'published'" size="sm" :color="n.status === 'review' ? 'warning' : 'neutral'" variant="subtle" :label="n.status === 'review' ? 'مراجعة' : 'مخفي'" />
        <UButton v-if="n.status === 'review'" size="xs" color="success" variant="soft" icon="i-lucide-check" label="تمام" @click="setStatus(n, 'published')" />
        <UButton v-if="n.status === 'review'" size="xs" color="neutral" variant="ghost" label="اخفيه" @click="setStatus(n, 'hidden')" />
        <UButton size="xs" color="neutral" variant="ghost" icon="i-lucide-pencil" aria-label="تعديل" @click="edit(n)" />
        <UButton v-if="!n.has_children" size="xs" color="error" variant="ghost" icon="i-lucide-trash-2" aria-label="امسح" @click="remove(n)" />
        <UIcon v-if="n.has_children && !reviewing && !q" name="i-lucide-chevron-left" class="size-4 text-(--ui-text-muted)" />
      </div>
    </UCard>

    <UModal v-model:open="formOpen" :title="form.id ? 'تعديل' : `ضيف تحت «${parent?.name ?? 'الجذر'}»`">
      <template #body>
        <form class="space-y-4" @submit.prevent="save">
          <UFormField v-if="!form.id" label="النوع">
            <USelect v-model="form.kind" :items="childKinds.map(k => ({ value: k.value, label: k.label }))" class="w-full" />
          </UFormField>
          <UFormField label="الاسم">
            <UInput v-model="form.name" class="w-full" />
          </UFormField>
          <UFormField label="الكود" hint="اختياري" description="كود مادة الجامعة مثلاً (CS101).">
            <UInput v-model="form.code" dir="ltr" class="w-full" />
          </UFormField>
          <UFormField label="الحالة">
            <USelect v-model="form.status" :items="[{ value: 'published', label: 'ظاهر' }, { value: 'review', label: 'مراجعة' }, { value: 'hidden', label: 'مخفي' }]" class="w-full" />
          </UFormField>
          <UAlert v-if="error" color="error" variant="subtle" :title="error" />
          <div class="flex justify-end gap-2">
            <UButton color="neutral" variant="ghost" label="إلغاء" @click="formOpen = false" />
            <UButton type="submit" :disabled="!form.name.trim()" label="حفظ" />
          </div>
        </form>
      </template>
    </UModal>
  </div>
</template>

<script setup lang="ts">
type Node = { id: string, parent_id: string | null, kind: string, kind_label: string, name: string, code: string | null, status: 'published' | 'review' | 'hidden', has_children: boolean, label?: string, suggested_by?: string | null }
type Kind = { value: string, label: string, children: string[], teachable: boolean }

const api = useAdminApi()
const toast = useToast()
const config = useRuntimeConfig()
const reviewing = ref(false)
const q = ref('')
const parent = ref<Node | null>(null)
const path = ref<Node[]>([])

const kinds = ref<Kind[]>([])
onMounted(async () => {
  kinds.value = (await $fetch<{ data: Kind[] }>(`${config.public.apiBase}/public/curriculum/kinds`)).data
})
const childKinds = computed(() => {
  if (!parent.value) return kinds.value.filter(k => !kinds.value.some(o => o.children.includes(k.value)) || ['stage', 'university'].includes(k.value))
  const allowed = kinds.value.find(k => k.value === parent.value!.kind)?.children ?? []
  return kinds.value.filter(k => allowed.includes(k.value))
})

const { data, refresh } = await useAsyncData('curriculum-admin', () => api<{ data: Node[], meta: { review: number } }>('/curriculum', {
  query: reviewing.value ? { status: 'review' } : q.value.trim().length >= 2 ? { q: q.value.trim() } : { parent: parent.value?.id },
}), { watch: [reviewing, q, parent] })
const rows = computed(() => data.value?.data ?? [])

function go(node: Node | null) {
  if (node === null) {
    path.value = []
  }
  else {
    const at = path.value.findIndex(p => p.id === node.id)
    path.value = at >= 0 ? path.value.slice(0, at + 1) : [...path.value, node]
  }
  parent.value = node
}

const formOpen = ref(false)
const error = ref<string | null>(null)
const form = reactive({ id: '', kind: '', name: '', code: '', status: 'published' })
function edit(n: Node | null) {
  Object.assign(form, n ? { id: n.id, kind: n.kind, name: n.name, code: n.code ?? '', status: n.status } : { id: '', kind: childKinds.value[0]?.value ?? '', name: '', code: '', status: 'published' })
  error.value = null
  formOpen.value = true
}
async function save() {
  try {
    const body = { name: form.name, code: form.code || null, status: form.status }
    if (form.id) await api(`/curriculum/${form.id}`, { method: 'PATCH', body })
    else await api('/curriculum', { method: 'POST', body: { ...body, kind: form.kind, parent_id: parent.value?.id ?? null } })
    formOpen.value = false
    await refresh()
  }
  catch (e) {
    error.value = (e as { data?: { message?: string } }).data?.message ?? 'حصلت مشكلة'
  }
}
async function setStatus(n: Node, status: Node['status']) {
  await api(`/curriculum/${n.id}`, { method: 'PATCH', body: { status } })
  await refresh()
}
async function remove(n: Node) {
  if (!confirm(`تمسح «${n.name}»؟`)) return
  try {
    await api(`/curriculum/${n.id}`, { method: 'DELETE' })
    await refresh()
  }
  catch (e) {
    toast.add({ color: 'error', title: (e as { data?: { message?: string } }).data?.message ?? 'ما ينفعش يتمسح' })
  }
}
</script>
