<template>
  <div class="space-y-6">
    <PageHeader title="الكورسات" description="كل كورس على مادة من المنهج، فيه فصول ودروس: فيديو، ملزمة، امتحان.">
      <UButton v-if="store.can('courses.manage')" icon="i-lucide-book-plus" label="كورس جديد" @click="creating = true" />
    </PageHeader>

    <div class="flex gap-1 rounded-(--ui-radius) bg-(--ui-bg-elevated) p-1 w-fit" role="group">
      <UButton v-for="s in statuses" :key="s.value" size="sm" :color="status === s.value ? 'primary' : 'neutral'" :variant="status === s.value ? 'solid' : 'ghost'" :label="s.label" @click="status = s.value" />
    </div>

    <USkeleton v-if="pending" class="h-48" />
    <div v-else-if="!courses?.length" class="app-card grid place-items-center gap-3 p-10 text-center">
      <UIcon name="i-lucide-book-open-text" class="size-12 text-(--ui-text-muted)" />
      <p class="font-bold">
        لسه مفيش كورسات
      </p>
      <p class="text-sm text-(--ui-text-muted)">
        ابدأ بكورس، وضيف فيه أول درس فيديو، وانشره لطلابك.
      </p>
      <UButton v-if="store.can('courses.manage')" icon="i-lucide-book-plus" label="اعمل أول كورس" @click="creating = true" />
    </div>
    <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
      <NuxtLink v-for="c in courses" :key="c.id" :to="`/courses/${c.id}`" class="app-card flex flex-col gap-2 p-4 transition hover:ring-1 hover:ring-primary">
        <div class="flex items-start justify-between gap-2">
          <h2 class="font-bold leading-snug">
            {{ c.title }}
          </h2>
          <UBadge :color="COURSE_STATUS[c.status].color" variant="subtle" :label="COURSE_STATUS[c.status].label" />
        </div>
        <p class="truncate text-xs text-(--ui-text-muted)">
          {{ c.grade }}
        </p>
        <div class="mt-auto flex flex-wrap gap-x-4 gap-y-1 text-sm text-(--ui-text-muted)">
          <span><span class="num font-bold text-(--ui-text)">{{ c.lessons_count }}</span> درس</span>
          <span class="num font-bold text-(--ui-text)">{{ c.price ? formatMoney(c.price) : 'مجاني' }}</span>
          <span>{{ accessLabel(c.access_days) }}</span>
        </div>
      </NuxtLink>
    </div>

    <UModal v-model:open="creating" title="كورس جديد" :ui="{ content: 'sm:max-w-2xl' }">
      <template #body>
        <form class="space-y-4" @submit.prevent="create">
          <UFormField label="المادة" :error="errors.curriculum_node_id" description="من شجرة مناهج مصر. مش لاقي مادتك؟ ابعتلنا ملاحظة ونضيفها.">
            <div v-if="node" class="flex items-center gap-2 rounded-(--ui-radius) bg-(--app-primary-soft) p-3">
              <UIcon name="i-lucide-graduation-cap" class="size-5 text-(--ui-primary)" />
              <span class="flex-1 text-sm font-bold">{{ node.label }}</span>
              <UButton size="xs" color="neutral" variant="ghost" label="غيّر" @click="node = null" />
            </div>
            <CurriculumPicker v-else @pick="n => { node = n; if (!form.title) form.title = n.name }" />
          </UFormField>
          <UFormField label="اسم الكورس" :error="errors.title">
            <UInput v-model="form.title" class="w-full" placeholder="فيزياء تالتة ثانوي — الترم الأول" />
          </UFormField>
          <div class="grid gap-3 sm:grid-cols-2">
            <UFormField label="السعر" description="0 = مجاني" :error="errors.price">
              <UInputNumber v-model="form.pounds" :min="0" class="w-full" :format-options="{ maximumFractionDigits: 2 }" />
            </UFormField>
            <UFormField label="مدة الاشتراك" :error="errors.access_days">
              <USelect v-model="form.access" :items="accessOptions" class="w-full" />
            </UFormField>
          </div>
          <UAlert v-if="error" color="error" variant="subtle" :title="error" />
          <div class="flex justify-end gap-2">
            <UButton color="neutral" variant="ghost" label="إلغاء" @click="creating = false" />
            <UButton type="submit" :loading="saving" :disabled="!node" label="اعمل الكورس" />
          </div>
        </form>
      </template>
    </UModal>
  </div>
</template>

<script setup lang="ts">
import type { CourseSummary, CurriculumNode } from '~/types/manhagy'

definePageMeta({ permission: 'courses.view' })

const api = useApi()
const store = useSessionStore()
const route = useRoute()

const statuses = [
  { value: '', label: 'الكل' },
  { value: 'published', label: 'المنشورة' },
  { value: 'draft', label: 'المسودات' },
  { value: 'archived', label: 'المقفولة' },
] as const
const status = ref<'' | CourseSummary['status']>('')
const { data: courses, pending } = await useAsyncData('courses', async () => (await api<{ data: CourseSummary[] }>('/courses', { query: { status: status.value || undefined } })).data, { watch: [status] })

const creating = ref(route.query.new === '1')
const node = ref<(CurriculumNode & { label: string }) | null>(null)
const accessOptions = [
  { value: '30', label: 'شهر' },
  { value: '90', label: 'ترم (3 شهور)' },
  { value: '270', label: 'سنة دراسية' },
  { value: 'none', label: 'لحد ما أقفل الكورس' },
]
const form = reactive({ title: '', pounds: 0, access: 'none' })
const saving = ref(false)
const error = ref<string | null>(null)
const errors = ref<Record<string, string>>({})

async function create() {
  if (!node.value) return
  saving.value = true
  error.value = null
  errors.value = {}
  try {
    const res = await api<{ data: { id: string } }>('/courses', { method: 'POST', body: {
      curriculum_node_id: node.value.id,
      title: form.title,
      price: Math.round(form.pounds * 100),
      access_days: form.access === 'none' ? null : Number(form.access),
    } })
    await navigateTo(`/courses/${res.data.id}`)
  }
  catch (e) {
    errors.value = apiValidationErrors(e)
    error.value = Object.keys(errors.value).length ? null : apiErrorMessage(e)
  }
  finally {
    saving.value = false
  }
}
</script>
