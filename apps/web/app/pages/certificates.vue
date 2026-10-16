<template>
  <div class="max-w-5xl space-y-6">
    <PageHeader title="الشهادات" description="شغّل الشهادة من صفحة الكورس. الطالب اللي يخلّص بياخدها من المنصة، وأي حد يقدر يتأكد منها بالكود." />

    <div class="flex flex-wrap gap-2">
      <UInput v-model="q" icon="i-lucide-search" placeholder="اسم الطالب أو كود الشهادة" class="w-full sm:w-72" />
      <USelect v-model="course" :items="courseItems" class="w-full sm:w-64" />
    </div>

    <p v-if="loaded && !rows.length" class="app-card p-8 text-center text-sm text-(--ui-text-muted)">
      لسه مفيش شهادات.
    </p>
    <div class="space-y-2">
      <div v-for="c in rows" :key="c.id" class="app-card flex flex-wrap items-center gap-3 p-3 text-sm" :class="c.revoked ? 'opacity-60' : ''">
        <UIcon name="i-lucide-award" class="size-6 text-(--ui-primary)" />
        <div class="min-w-0 flex-1">
          <p class="font-bold">
            {{ c.learner_name }}
          </p>
          <p class="text-(--ui-text-muted)">
            {{ c.course_title }} · {{ formatDate(c.issued_at) }}
            <template v-if="c.exam_percent !== null">
              · <span class="num">{{ c.exam_percent }}%</span>
            </template>
          </p>
          <p v-if="c.revoked" class="text-xs text-error">
            اتلغت: {{ c.revoke_reason }}
          </p>
        </div>
        <span class="num rounded bg-(--ui-bg-elevated) px-2 py-1 font-mono text-xs" dir="ltr">{{ c.display_code }}</span>
        <UButton size="sm" color="neutral" variant="ghost" icon="i-lucide-external-link" :to="c.url" target="_blank" aria-label="افتح الشهادة" />
        <UButton v-if="!c.revoked" size="sm" color="error" variant="ghost" icon="i-lucide-ban" label="إلغاء" @click="revoke(c)" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { IssuedCertificate } from '~/types/manhagy'

definePageMeta({ permission: 'certificates.manage' })

const api = useApi()
const route = useRoute()
const toast = useToast()
const rows = ref<IssuedCertificate[]>([])
const loaded = ref(false)
const q = ref('')
const course = ref<string>(String(route.query.course ?? 'all'))
const courses = ref<{ id: string, title: string }[]>([])
const courseItems = computed(() => [{ label: 'كل الكورسات', value: 'all' }, ...courses.value.map(c => ({ label: c.title, value: c.id }))])

async function load() {
  const query: Record<string, string> = {}
  if (q.value.trim()) query.q = q.value.trim()
  if (course.value !== 'all') query.course_id = course.value
  rows.value = (await api<{ data: IssuedCertificate[] }>('/certificates', { query })).data
  loaded.value = true
}

let timer: ReturnType<typeof setTimeout> | undefined
watch([q, course], () => {
  clearTimeout(timer)
  timer = setTimeout(load, 300)
})
onMounted(async () => {
  courses.value = (await api<{ data: { id: string, title: string }[] }>('/courses')).data
  await load()
})

async function revoke(c: IssuedCertificate) {
  const reason = prompt(`ليه بتلغي شهادة ${c.learner_name}؟ (السبب بيظهر لأي حد يتأكد منها إنها ملغية)`)
  if (!reason?.trim()) return
  try {
    await api(`/certificates/${c.id}/revoke`, { method: 'POST', body: { reason: reason.trim() } })
    toast.add({ color: 'success', title: 'الشهادة اتلغت' })
    await load()
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
}
</script>
