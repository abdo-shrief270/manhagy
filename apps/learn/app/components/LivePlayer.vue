<template>
  <div class="relative aspect-video w-full overflow-hidden rounded-xl bg-black select-none" @contextmenu.prevent>
    <video ref="video" class="h-full w-full" controls controlslist="nodownload noplaybackrate" disablepictureinpicture playsinline autoplay />
    <!-- The student's name and phone, moving around: a leaked recording says whose account it came from. -->
    <div v-if="watermark" class="pointer-events-none absolute text-sm font-bold text-white/35 transition-all duration-[1500ms] [text-shadow:0_1px_2px_rgba(0,0,0,.5)]" :style="{ top: `${pos.top}%`, left: `${pos.left}%` }" dir="rtl">
      {{ watermark }}
    </div>
    <span class="absolute start-3 top-3 rounded-md bg-red-600 px-2 py-0.5 text-xs font-bold text-white">● لايف</span>
    <div v-if="waiting" class="absolute inset-0 grid place-items-center bg-black/60 text-center text-white">
      <p class="text-sm">
        بنوصّل بالبث…
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import Hls from 'hls.js'

const props = defineProps<{ src: string, watermark: string | null }>()
const emit = defineEmits<{ broken: [] }>()
const video = ref<HTMLVideoElement | null>(null)
const waiting = ref(true)
let hls: Hls | null = null
let retries = 0

function attach() {
  const el = video.value
  if (!el) return
  if (Hls.isSupported()) {
    hls?.destroy()
    hls = new Hls({ lowLatencyMode: true, liveSyncDurationCount: 2, backBufferLength: 30 })
    hls.loadSource(props.src)
    hls.attachMedia(el)
    hls.on(Hls.Events.MANIFEST_PARSED, () => {
      waiting.value = false
      el.play().catch(() => {})
    })
    hls.on(Hls.Events.ERROR, (_, data) => {
      if (!data.fatal) return
      // The teacher's connection hiccuped: try again a few times before telling the page.
      if (data.type === Hls.ErrorTypes.NETWORK_ERROR && retries++ < 5) {
        waiting.value = true
        setTimeout(attach, 3000)
      }
      else if (data.type === Hls.ErrorTypes.MEDIA_ERROR) {
        hls?.recoverMediaError()
      }
      else {
        emit('broken')
      }
    })
  }
  else {
    el.src = props.src // Safari plays HLS itself
    el.addEventListener('loadedmetadata', () => (waiting.value = false), { once: true })
  }
}

function playing(): boolean {
  const el = video.value
  return !!el && !el.paused && !el.ended && el.readyState > 2
}

const pos = reactive({ top: 10, left: 10 })
let markTimer: ReturnType<typeof setInterval> | undefined
onMounted(() => {
  attach()
  const jump = () => {
    pos.top = 5 + Math.random() * 80
    pos.left = 5 + Math.random() * 60
  }
  jump()
  markTimer = setInterval(jump, 12000)
})
onBeforeUnmount(() => {
  hls?.destroy()
  clearInterval(markTimer)
})

defineExpose({ playing })
</script>
