<template>
  <div class="space-y-2">
    <div class="relative aspect-video overflow-hidden rounded-xl bg-black select-none" @contextmenu.prevent>
      <LiveTile v-if="main" :key="main.key" :track="main.track" :mirror="main.local && main.source === Track.Source.Camera" />
      <div v-else class="absolute inset-0 grid place-items-center p-4 text-center text-white/70">
        <slot name="empty">
          <p class="text-sm">
            مستنيين الصورة…
          </p>
        </slot>
      </div>
      <!-- The teacher's camera in a corner while the screen is shown. -->
      <div v-if="corner" class="absolute end-3 bottom-3 aspect-square w-[22%] overflow-hidden rounded-full border-2 border-white/70 shadow-lg">
        <LiveTile :key="corner.key" :track="corner.track" fit="cover" :mirror="corner.local" />
      </div>
      <slot name="overlay" />
    </div>

    <!-- Students with the floor. -->
    <div v-if="speakers.length" class="flex gap-2 overflow-x-auto">
      <div v-for="s in speakers" :key="s.identity" class="relative aspect-video w-36 shrink-0 overflow-hidden rounded-lg bg-(--ui-bg-elevated) ring-2" :class="speaking.includes(s.identity) ? 'ring-(--ui-primary)' : 'ring-transparent'">
        <LiveTile v-if="s.video" :key="s.video.key" :track="s.video.track" fit="cover" :mirror="s.video.local" />
        <div v-else class="grid h-full place-items-center">
          <UIcon name="i-lucide-mic" class="size-6 text-(--ui-text-muted)" />
        </div>
        <span class="absolute inset-x-1 bottom-1 truncate rounded bg-black/55 px-1 text-xs text-white">{{ s.name }}</span>
      </div>
    </div>

    <!-- Everyone else's sound (never our own). -->
    <LiveTile v-for="a in audio" :key="a.key" :track="a.track" />
  </div>
</template>

<script setup lang="ts">
import { Track } from 'livekit-client'
import type { LiveTrack } from '~/composables/useLiveRoom'

const props = defineProps<{ tracks: LiveTrack[], speaking: string[] }>()

const video = computed(() => props.tracks.filter(t => t.track.kind === 'video' && !t.muted))
const screenShare = computed(() => video.value.find(t => t.source === Track.Source.ScreenShare && t.teacher) ?? video.value.find(t => t.source === Track.Source.ScreenShare))
const teacherCam = computed(() => video.value.find(t => t.teacher && t.source === Track.Source.Camera))
const main = computed(() => screenShare.value ?? teacherCam.value ?? null)
const corner = computed(() => (screenShare.value && teacherCam.value ? teacherCam.value : null))
const audio = computed(() => props.tracks.filter(t => t.track.kind === 'audio' && !t.local))
const speakers = computed(() => {
  const students = new Map<string, { identity: string, name: string, video: LiveTrack | null }>()
  for (const t of props.tracks) {
    if (t.teacher || t.source === Track.Source.ScreenShare || t.source === Track.Source.ScreenShareAudio) continue
    const s = students.get(t.identity) ?? { identity: t.identity, name: t.name, video: null }
    if (t.track.kind === 'video' && !t.muted) s.video = t
    students.set(t.identity, s)
  }
  // Only who's actually sending: a track the server is taking away is shown no more.
  return [...students.values()].filter(s => s.video || props.tracks.some(t => t.identity === s.identity && t.track.kind === 'audio' && !t.muted))
})
</script>
