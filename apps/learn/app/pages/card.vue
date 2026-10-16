<template>
  <div class="mx-auto max-w-sm space-y-4">
    <USkeleton v-if="pending" class="h-80" />
    <UCard v-else-if="!card" :ui="{ body: 'text-center' }">
      <p class="text-(--ui-text-muted)">
        المنصة دي مالهاش سنتر.
      </p>
    </UCard>
    <template v-else>
      <div class="app-card space-y-4 p-6 text-center">
        <p class="text-sm text-(--ui-text-muted)">
          {{ site?.title }}
        </p>
        <!-- eslint-disable-next-line vue/no-v-html -->
        <div class="mx-auto size-56 rounded-xl bg-white p-3 [&>svg]:h-full [&>svg]:w-full" v-html="qr" />
        <p class="text-xl font-extrabold">
          {{ card.name }}
        </p>
        <p class="text-sm text-(--ui-text-muted)">
          ورّي الكود ده على الباب، أو استخدم الكارنيه المطبوع.
        </p>
      </div>
      <UCard v-if="card.groups.length" :ui="{ body: 'p-0 sm:p-0' }">
        <template #header>
          <h2 class="font-bold">
            مجموعاتك
          </h2>
        </template>
        <div v-for="(g, i) in card.groups" :key="i" class="border-b border-(--ui-border) px-4 py-3 text-sm last:border-0">
          <p class="font-bold">
            {{ g.name }}
          </p>
          <p class="text-(--ui-text-muted)">
            {{ g.schedule }}<template v-if="g.room">
              · {{ g.room }}
            </template>
          </p>
        </div>
      </UCard>
    </template>
  </div>
</template>

<script setup lang="ts">
import QRCode from 'qrcode'

definePageMeta({ auth: true })

const { site } = useSite()
const api = useLearnApi()
const { data: card, pending } = await useAsyncData('center-card', async () => (await api<{ data: { name: string, token: string, groups: { name: string, room: string | null, schedule: string }[] } }>('/learner/center/card').catch(() => ({ data: null }))).data)
const qr = ref('')
watchEffect(async () => {
  qr.value = card.value ? await QRCode.toString(card.value.token, { type: 'svg', margin: 0, errorCorrectionLevel: 'M' }) : ''
})
</script>
