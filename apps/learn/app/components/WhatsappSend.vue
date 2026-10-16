<template>
  <div class="space-y-4 text-center">
    <p class="leading-loose">
      ابعت الرسالة دي على واتساب <b>من نفس رقمك</b> للرقم <bdi class="num font-bold">{{ localPhone(`+${verification.whatsapp}`) }}</bdi>. مش محتاج تستنى رد.
    </p>
    <div class="num rounded-(--ui-radius) bg-(--ui-bg-elevated) p-3 text-lg font-bold" dir="rtl">
      {{ verification.text }}
    </div>
    <UButton :to="verification.link" target="_blank" block size="xl" color="success" icon="i-lucide-message-circle" label="افتح واتساب وابعت" />
    <p class="flex items-center justify-center gap-2 text-sm text-(--ui-text-muted)">
      <UIcon name="i-lucide-loader-circle" class="size-4 animate-spin" />
      مستنيين رسالتك…
    </p>
  </div>
</template>

<script setup lang="ts">
import type { Verification } from '~/utils/types'

defineProps<{ verification: Verification }>()
</script>
