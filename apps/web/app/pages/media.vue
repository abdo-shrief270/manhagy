<template>
  <div class="max-w-5xl space-y-6">
    <PageHeader title="الفيديوهات والملازم" description="الفيديو بيترفع على طول لمزوّد الفيديو المحمي، ومحدش يقدر يحمّله. الملازم PDF بتتفتح بلينك بيقفل بعد دقايق.">
      <UButton icon="i-lucide-upload" label="ارفع فيديو أو PDF" :loading="uploader.uploading.value" @click="input?.click()" />
      <input ref="input" type="file" class="hidden" accept="video/*,application/pdf" multiple @change="onFiles">
    </PageHeader>

    <div v-if="queue.length" class="app-card space-y-2 p-4">
      <p class="text-sm font-bold">
        بيترفع: {{ queue[0] }} <span v-if="queue.length > 1" class="text-(--ui-text-muted)">(وكمان {{ queue.length - 1 }})</span>
      </p>
      <UProgress :model-value="uploader.progress.value" />
    </div>

    <div class="flex gap-1 rounded-(--ui-radius) bg-(--ui-bg-elevated) p-1 w-fit">
      <UButton v-for="k in kinds" :key="k.value" size="sm" :color="kind === k.value ? 'primary' : 'neutral'" :variant="kind === k.value ? 'solid' : 'ghost'" :label="k.label" @click="kind = k.value" />
    </div>

    <UCard :ui="{ body: 'p-0 sm:p-0' }">
      <div v-if="!assets?.length" class="p-10 text-center text-(--ui-text-muted)">
        المكتبة فاضية. ارفع أول فيديو.
      </div>
      <div v-else class="divide-y divide-(--ui-border)">
        <div v-for="a in assets" :key="a.id" class="flex items-center gap-3 px-4 py-3">
          <UIcon :name="a.kind === 'video' ? 'i-lucide-play' : 'i-lucide-file-text'" class="size-5 text-(--ui-text-muted)" />
          <div class="min-w-0 flex-1">
            <p class="truncate font-bold">
              {{ a.title }}
            </p>
            <p class="text-xs text-(--ui-text-muted)">
              <span class="num">{{ formatDate(a.created_at) }}</span>
              <template v-if="a.duration_seconds">
                · <span class="num">{{ formatDuration(a.duration_seconds) }}</span>
              </template>
              <template v-if="a.size_bytes">
                · <span class="num">{{ (a.size_bytes / 1048576).toFixed(1) }} MB</span>
              </template>
            </p>
          </div>
          <UBadge v-if="a.status !== 'ready'" :color="a.status === 'failed' ? 'error' : 'warning'" variant="subtle" :label="a.status === 'failed' ? 'فشل' : 'بيتجهّز'" />
          <UButton v-if="a.status !== 'ready' && a.status !== 'failed'" size="xs" color="neutral" variant="ghost" icon="i-lucide-refresh-cw" aria-label="حدّث" @click="check(a.id)" />
        </div>
      </div>
    </UCard>
  </div>
</template>

<script setup lang="ts">
import type { MediaAsset } from '~/types/manhagy'

definePageMeta({ permission: 'media.upload' })

const api = useApi()
const toast = useToast()
const uploader = useMediaUpload()
const input = ref<HTMLInputElement | null>(null)
const queue = ref<string[]>([])
const kinds = [{ value: '', label: 'الكل' }, { value: 'video', label: 'فيديوهات' }, { value: 'file', label: 'ملازم' }] as const
const kind = ref<'' | 'video' | 'file'>('')
const { data: assets, refresh } = await useAsyncData('media', async () => (await api<{ data: MediaAsset[] }>('/media', { query: { kind: kind.value || undefined } })).data, { watch: [kind] })

async function onFiles(e: Event) {
  const files = [...((e.target as HTMLInputElement).files ?? [])]
  queue.value = files.map(f => f.name)
  for (const file of files) {
    try {
      await uploader.upload(file, file.name.replace(/\.[^.]+$/, ''))
    }
    catch (err) {
      toast.add({ color: 'error', title: `«${file.name}»: ${apiErrorMessage(err)}` })
    }
    queue.value.shift()
    await refresh()
  }
  if (input.value) input.value.value = ''
}

async function check(id: string) {
  await api(`/media/${id}/check`, { method: 'POST' }).catch(() => {})
  await refresh()
}
</script>
