<template>
  <footer class="border-t border-line bg-white">
    <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-6 gap-y-2 px-4 py-6 text-sm text-muted">
      <span>سوق منهجي — كورسات المدرسين والمدربين في مكان واحد.</span>
      <NuxtLink to="/verify">اتأكد من شهادة</NuxtLink>
      <a :href="siteUrl" class="ms-auto font-bold">انت مدرس أو مدرب؟ افتح منصتك</a>
    </div>
  </footer>
</template>

<script setup lang="ts">
const siteUrl = String(useRuntimeConfig().public.siteUrl)
</script>
