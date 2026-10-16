<template>
  <div class="mx-auto max-w-lg space-y-6 text-center">
    <h1 class="text-2xl font-extrabold">
      اتأكد من شهادة
    </h1>
    <p class="text-muted">
      اكتب الكود المكتوب على الشهادة (10 حروف وأرقام) عشان تتأكد إنها صادرة فعلاً من منصة على منهجي.
    </p>
    <form class="flex gap-2" @submit.prevent="go">
      <input v-model="code" class="num min-w-0 flex-1 rounded-xl border border-line bg-white px-4 py-3 text-center font-mono text-lg uppercase tracking-widest outline-none focus:border-brand" dir="ltr" placeholder="XXXXX-XXXXX" aria-label="كود الشهادة">
      <button type="submit" class="btn" :disabled="clean.length !== 10">
        اتأكد
      </button>
    </form>
  </div>
</template>

<script setup lang="ts">
const code = ref('')
const clean = computed(() => code.value.toUpperCase().replace(/[^A-Z0-9]/g, ''))

function go() {
  if (clean.value.length === 10) navigateTo(`/verify/${clean.value}`)
}
useSeoMeta({ title: 'اتأكد من شهادة', description: 'اتأكد من شهادة إتمام كورس صادرة من منصة على منهجي بالكود بتاعها.' })
</script>
