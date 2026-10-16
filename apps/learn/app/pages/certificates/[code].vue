<template>
  <div class="mx-auto max-w-5xl space-y-4 p-4 print:p-0">
    <USkeleton v-if="pending" class="h-96 w-full" />
    <div v-else-if="!cert" class="app-card space-y-2 p-8 text-center">
      <UIcon name="i-lucide-circle-x" class="size-10 text-error" />
      <h1 class="text-xl font-bold">
        مفيش شهادة بالكود ده
      </h1>
      <p class="text-sm text-(--ui-text-muted)">
        اتأكد من الكود المكتوب على الشهادة.
      </p>
    </div>
    <template v-else>
      <UAlert v-if="cert.revoked" color="error" variant="subtle" icon="i-lucide-ban" title="الشهادة دي اتلغت" description="المدرس لغاها، فهي مش معتمدة." class="print:hidden" />
      <UAlert v-else color="success" variant="subtle" icon="i-lucide-badge-check" title="شهادة سليمة" :description="`صادرة من منصة ${cert.site.title} على منهجي.`" class="print:hidden" />

      <article class="certificate relative w-full sm:aspect-[1.414] overflow-hidden rounded-2xl bg-white text-slate-900 shadow-sm print:rounded-none print:shadow-none" :class="cert.revoked ? 'grayscale' : ''">
        <div class="absolute inset-3 rounded-xl border-4 border-double border-(--ui-primary) sm:inset-5" />
        <div class="relative flex h-full flex-col items-center justify-between gap-8 px-8 py-10 text-center sm:gap-0 sm:px-16 sm:py-12">
          <p class="text-sm font-bold tracking-wide text-(--ui-primary) sm:text-lg">
            {{ cert.site.title }}
          </p>
          <div class="space-y-2 sm:space-y-4">
            <h1 class="text-2xl font-extrabold sm:text-5xl">
              {{ cert.title }}
            </h1>
            <p class="text-sm text-slate-500 sm:text-lg">
              بنشهد إن
            </p>
            <p class="text-xl font-extrabold text-(--ui-primary) sm:text-4xl">
              {{ cert.learner_name }}
            </p>
            <p class="text-sm text-slate-500 sm:text-lg">
              خلّص كورس
            </p>
            <p class="text-lg font-bold sm:text-3xl">
              «{{ cert.course_title }}»
            </p>
            <p class="text-xs text-slate-500 sm:text-base">
              <template v-if="cert.minutes">
                <span class="num">{{ hours }}</span> من الشرح
              </template>
              <template v-if="cert.exam_percent !== null">
                · متوسط الامتحانات <span class="num">{{ cert.exam_percent }}%</span>
              </template>
            </p>
          </div>
          <div class="flex w-full items-end justify-between gap-4 text-start">
            <div class="text-xs sm:text-sm">
              <p class="text-slate-500">
                التاريخ
              </p>
              <p class="num font-bold">
                {{ formatDate(cert.issued_at) }}
              </p>
            </div>
            <div class="flex flex-col items-center gap-1">
              <!-- eslint-disable-next-line vue/no-v-html -->
              <div class="size-14 sm:size-20" aria-label="QR للتحقق" v-html="qr" />
              <p class="num font-mono text-[10px] sm:text-xs" dir="ltr">
                {{ cert.display_code }}
              </p>
            </div>
            <div class="text-end text-xs sm:text-sm">
              <template v-if="cert.signer_name">
                <p class="font-bold">
                  {{ cert.signer_name }}
                </p>
                <p v-if="cert.signer_title" class="text-slate-500">
                  {{ cert.signer_title }}
                </p>
              </template>
              <p v-else class="font-bold">
                {{ cert.teacher_name }}
              </p>
            </div>
          </div>
        </div>
      </article>

      <div class="flex flex-wrap justify-center gap-2 print:hidden">
        <UButton icon="i-lucide-printer" label="اطبع أو احفظ PDF" @click="print" />
        <UButton color="success" variant="soft" icon="i-lucide-message-circle" label="ابعتها" :to="`https://wa.me/?text=${encodeURIComponent(`شهادة «${cert.course_title}»: ${cert.url}`)}`" target="_blank" />
        <UButton color="neutral" variant="outline" :icon="copied ? 'i-lucide-check' : 'i-lucide-copy'" label="انسخ اللينك" @click="copy" />
      </div>
      <p class="text-center text-xs text-(--ui-text-muted) print:hidden">
        أي حد يقدر يتأكد من الشهادة من اللينك ده أو بالكود <span class="num font-mono" dir="ltr">{{ cert.display_code }}</span>.
      </p>
    </template>
  </div>
</template>

<script setup lang="ts">
import QRCode from 'qrcode'

definePageMeta({ layout: 'plain' })

interface PublicCertificate {
  code: string
  display_code: string
  title: string
  learner_name: string
  course_title: string
  teacher_name: string
  signer_name: string | null
  signer_title: string | null
  minutes: number
  exam_percent: number | null
  issued_at: string
  revoked: boolean
  url: string
  site: { title: string, url: string, color: string }
}

const code = String(useRoute().params.code)
const api = useLearnApi()
const copied = ref(false)
const qr = ref('')
const { data: cert, pending } = await useAsyncData(`certificate-${code}`, async () => {
  try {
    return (await api<{ data: PublicCertificate }>(`/public/certificates/${encodeURIComponent(code)}`)).data
  }
  catch {
    return null
  }
})

watchEffect(async () => {
  qr.value = cert.value ? await QRCode.toString(cert.value.url, { type: 'svg', margin: 0, errorCorrectionLevel: 'M' }) : ''
})

const hours = computed(() => {
  const m = cert.value?.minutes ?? 0
  return m < 60 ? `${m} دقيقة` : `${Math.round(m / 6) / 10} ساعة`
})

useHead(() => ({ title: cert.value ? `${cert.value.title} — ${cert.value.learner_name}` : 'شهادة' }))

function print() {
  window.print()
}

async function copy() {
  if (!cert.value) return
  await navigator.clipboard.writeText(cert.value.url).catch(() => {})
  copied.value = true
  setTimeout(() => (copied.value = false), 2000)
}
</script>

<style>
@media print {
  @page { size: A4 landscape; margin: 0; }
  .certificate { width: 297mm; height: 210mm; aspect-ratio: auto; }
}
</style>
