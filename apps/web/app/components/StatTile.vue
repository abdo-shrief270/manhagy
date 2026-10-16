<template>
  <div class="app-card p-4">
    <div class="flex items-center justify-between gap-2">
      <p class="text-sm text-(--ui-text-muted)">
        {{ label }}
      </p>
      <UIcon v-if="icon" :name="icon" class="size-4 text-(--ui-text-dimmed)" />
    </div>
    <p class="num mt-1 text-2xl font-extrabold">
      {{ value }}
    </p>
    <p v-if="hint" class="mt-0.5 truncate text-xs text-(--ui-text-muted)">
      {{ hint }}
    </p>
  </div>
</template>

<script setup lang="ts">
defineProps<{ label: string, value: string, hint?: string, icon?: string }>()
</script>
