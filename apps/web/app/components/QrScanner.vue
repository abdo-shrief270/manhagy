<template>
  <div class="space-y-2">
    <div v-if="supported" class="relative aspect-video overflow-hidden rounded-xl bg-black">
      <video ref="video" class="h-full w-full object-cover" muted playsinline />
      <div class="pointer-events-none absolute inset-8 rounded-xl border-2 border-white/60" />
      <UButton v-if="!running" class="absolute inset-0 m-auto h-fit w-fit" icon="i-lucide-camera" label="شغّل الكاميرا" @click="start" />
    </div>
    <!-- A USB / Bluetooth scanner types the code and Enter. -->
    <form @submit.prevent="typed">
      <UInput ref="field" v-model="text" icon="i-lucide-scan-line" class="w-full" :placeholder="supported ? 'أو امسح بالجهاز / اكتب الكود' : 'امسح بالجهاز أو اكتب الكود'" autocomplete="off" dir="ltr" />
    </form>
  </div>
</template>

<script setup lang="ts">
/** Reads QR codes: the camera (BarcodeDetector) where the browser has it, else a scanner that types. */
const emit = defineEmits<{ read: [value: string] }>()

const video = ref<HTMLVideoElement | null>(null)
const text = ref('')
const running = ref(false)
const supported = typeof window !== 'undefined' && 'BarcodeDetector' in window
let stream: MediaStream | null = null
let loop = 0
let last = { value: '', at: 0 }

function emitRead(value: string) {
  const now = Date.now()
  // The camera sees the same card for a while: one read per card per 4 s.
  if (value === last.value && now - last.at < 4000) return
  last = { value, at: now }
  emit('read', value)
}

async function start() {
  try {
    stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
    video.value!.srcObject = stream
    await video.value!.play()
    running.value = true
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const detector = new (window as any).BarcodeDetector({ formats: ['qr_code'] })
    const tick = async () => {
      if (!running.value || !video.value) return
      const codes = await detector.detect(video.value).catch(() => [])
      if (codes[0]?.rawValue) emitRead(codes[0].rawValue)
      loop = requestAnimationFrame(() => setTimeout(tick, 150))
    }
    tick()
  }
  catch {
    running.value = false
  }
}

function typed() {
  if (text.value.trim()) emitRead(text.value.trim())
  text.value = ''
}

onBeforeUnmount(() => {
  running.value = false
  cancelAnimationFrame(loop)
  stream?.getTracks().forEach(t => t.stop())
})
</script>
