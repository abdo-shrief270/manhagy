<template>
  <div class="space-y-3">
    <div class="flex gap-2">
      <USelectMenu
        :model-value="selected ?? undefined"
        @update:model-value="(v: string) => selected = v"
        :items="items"
        value-key="id"
        label-key="title"
        class="flex-1"
        :placeholder="kind === 'video' ? 'اختار فيديو من مكتبتك' : 'اختار ملزمة من مكتبتك'"
        :search-input="{ placeholder: 'دوّر…' }"
      >
        <template #item-trailing="{ item }">
          <UBadge v-if="item.status !== 'ready'" size="sm" color="warning" variant="subtle" label="بيتجهّز" />
          <span v-else-if="item.duration_seconds" class="num text-xs text-(--ui-text-muted)">{{ formatDuration(item.duration_seconds) }}</span>
        </template>
      </USelectMenu>
      <UButton color="neutral" variant="outline" icon="i-lucide-upload" label="ارفع جديد" :loading="uploader.uploading.value" @click="input?.click()" />
      <input ref="input" type="file" class="hidden" :accept="kind === 'video' ? 'video/*' : 'application/pdf'" @change="onFile">
    </div>
    <UProgress v-if="uploader.uploading.value" :model-value="uploader.progress.value" />
    <p v-if="error" class="text-sm text-(--ui-error)">
      {{ error }}
    </p>
  </div>
</template>

<script setup lang="ts">
import type { MediaAsset } from '~/types/manhagy'

const props = defineProps<{ kind: 'video' | 'pdf' }>()
const selected = defineModel<string | null>({ default: null })

const api = useApi()
const uploader = useMediaUpload()
const input = ref<HTMLInputElement | null>(null)
const error = ref<string | null>(null)
const library = ref<MediaAsset[]>([])

const items = computed(() => library.value.filter(a => a.kind === (props.kind === 'video' ? 'video' : 'file')))

async function load() {
  library.value = (await api<{ data: MediaAsset[] }>('/media', { query: { kind: props.kind === 'video' ? 'video' : 'file' } })).data
}
onMounted(load)

async function onFile(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  error.value = null
  try {
    const asset = await uploader.upload(file, file.name.replace(/\.[^.]+$/, ''))
    library.value = [asset, ...library.value]
    selected.value = asset.id
  }
  catch (err) {
    error.value = apiErrorMessage(err)
  }
  finally {
    if (input.value) input.value.value = ''
  }
}
</script>
