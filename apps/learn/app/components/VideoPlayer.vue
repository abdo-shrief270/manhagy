<template>
  <div ref="box" class="relative aspect-video w-full overflow-hidden rounded-xl bg-black select-none" @contextmenu.prevent>
    <video v-if="!demo" ref="video" class="h-full w-full" :poster="poster ?? undefined" controls controlslist="nodownload noplaybackrate" disablepictureinpicture playsinline />
    <div v-else class="grid h-full w-full place-items-center text-center text-white/80">
      <div class="space-y-2">
        <UIcon name="i-lucide-clapperboard" class="mx-auto size-12" />
        <p class="font-bold">
          فيديو تجريبي
        </p>
        <p class="text-sm text-white/60">
          ده فيديو تجريبي للتطوير. المشاهدة بتتحسب كأنك بتتفرّج.
        </p>
        <UButton size="sm" color="neutral" :label="demoPlaying ? 'وقّف' : 'شغّل'" @click="demoPlaying = !demoPlaying" />
      </div>
    </div>

    <!-- The student's name and phone, moving around: a leaked copy says whose account it came from. -->
    <div v-if="watermark" class="pointer-events-none absolute text-sm font-bold text-white/35 transition-all duration-[1500ms] [text-shadow:0_1px_2px_rgba(0,0,0,.5)]" :style="{ top: `${pos.top}%`, left: `${pos.left}%` }" dir="rtl">
      {{ watermark.text }}
    </div>

    <div v-if="ended" class="absolute inset-0 grid place-items-center bg-black/80 p-6 text-center text-white">
      <div class="space-y-3">
        <UIcon name="i-lucide-circle-pause" class="mx-auto size-10" />
        <p class="font-bold">
          {{ ended }}
        </p>
        <UButton color="neutral" label="كمّل هنا" @click="emit('reopen')" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import Hls from 'hls.js'

const props = defineProps<{ src: string, poster?: string | null, startAt: number, watermark: { text: string } | null, ended: string | null }>()
const emit = defineEmits<{ reopen: [], broken: [] }>()

const video = ref<HTMLVideoElement | null>(null)
const box = ref<HTMLDivElement | null>(null)
let hls: Hls | null = null

// The development host hands out addresses that don't exist.
const demo = computed(() => new URL(props.src).hostname.endsWith('.test'))
const demoPlaying = ref(true)
const demoPosition = ref(props.startAt)
let demoTimer: ReturnType<typeof setInterval> | undefined

function attach(src: string, at: number) {
  const el = video.value
  if (!el) return
  if (src.includes('.m3u8') && Hls.isSupported()) {
    hls?.destroy()
    hls = new Hls()
    hls.loadSource(src)
    hls.attachMedia(el)
    // The key or a playlist refused (the session ended elsewhere, or the link is old): ask for a new one.
    hls.on(Hls.Events.ERROR, (_, data) => {
      if (data.fatal) emit('broken')
    })
  }
  else {
    el.src = src
  }
  el.addEventListener('loadedmetadata', () => {
    if (at > 5) el.currentTime = at
  }, { once: true })
}

onMounted(() => {
  if (demo.value) {
    demoTimer = setInterval(() => demoPlaying.value && demoPosition.value++, 1000)
  }
  else {
    attach(props.src, props.startAt)
  }
  moveMark()
})
onBeforeUnmount(() => {
  hls?.destroy()
  clearInterval(demoTimer)
  clearInterval(markTimer)
})

watch(() => props.ended, (reason) => {
  if (reason) video.value?.pause()
})

/** For the heartbeat. */
function state(): { position: number, playing: boolean } {
  if (demo.value) return { position: demoPosition.value, playing: demoPlaying.value && !props.ended }
  const el = video.value
  return { position: Math.floor(el?.currentTime ?? 0), playing: !!el && !el.paused && !el.ended }
}

/** A fresh link (the old one expires in minutes): only reloaded if playback broke. */
function refresh(src: string) {
  if (demo.value || !video.value) return
  if (video.value.error) attach(src, video.value.currentTime)
}

const pos = reactive({ top: 10, left: 10 })
let markTimer: ReturnType<typeof setInterval> | undefined
function moveMark() {
  const jump = () => {
    pos.top = 5 + Math.random() * 80
    pos.left = 5 + Math.random() * 60
  }
  jump()
  markTimer = setInterval(jump, 12000)
}

defineExpose({ state, refresh })
</script>
