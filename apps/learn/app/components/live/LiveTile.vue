<template>
  <audio v-if="track.kind === 'audio'" ref="el" autoplay />
  <video v-else ref="el" class="h-full w-full" :class="[fit === 'cover' ? 'object-cover' : 'object-contain', mirror ? '-scale-x-100' : '']" autoplay muted playsinline disablepictureinpicture />
</template>

<script setup lang="ts">
import type { Track } from 'livekit-client'

/** One LiveKit track in a <video> / <audio> (attached by the SDK, which also tells adaptive stream the size it's shown at). */
const props = withDefaults(defineProps<{ track: Track, fit?: 'contain' | 'cover', mirror?: boolean }>(), { fit: 'contain', mirror: false })
const el = ref<HTMLMediaElement | null>(null)

onMounted(() => el.value && props.track.attach(el.value))
watch(() => props.track, (now, before) => {
  if (!el.value) return
  before?.detach(el.value)
  now.attach(el.value)
})
onBeforeUnmount(() => el.value && props.track.detach(el.value))
</script>
