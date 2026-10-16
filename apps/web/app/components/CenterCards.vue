<template>
  <PrintSheet page-size="A4" margin="8mm">
    <div class="center-cards">
      <div v-for="c in cards" :key="c.learner_id" class="center-card">
        <div class="center-card__head">
          {{ center }}
        </div>
        <div class="center-card__body">
          <PrintQrCode :value="c.token" :size="86" />
          <div>
            <p class="center-card__name">
              {{ c.name }}
            </p>
            <p class="center-card__group">
              {{ c.group }}
            </p>
            <p class="center-card__phone num">
              {{ localPhone(c.phone) }}
            </p>
          </div>
        </div>
      </div>
    </div>
  </PrintSheet>
</template>

<script setup lang="ts">
/** Students' cards (8 a page, credit-card sized): the QR the door scans. */
defineProps<{ cards: { learner_id: string, name: string, phone: string | null, group: string, token: string }[], center: string }>()
</script>

<style>
.center-cards { display: grid; grid-template-columns: 85.6mm 85.6mm; gap: 6mm; justify-content: center; font-family: Cairo, sans-serif; color: #000; }
.center-card { width: 85.6mm; height: 54mm; border: 1px solid #999; border-radius: 3mm; padding: 3mm 4mm; display: flex; flex-direction: column; gap: 2mm; break-inside: avoid; }
.center-card__head { font-size: 10pt; font-weight: 800; border-bottom: 1px solid #ddd; padding-bottom: 1mm; }
.center-card__body { display: flex; gap: 4mm; align-items: center; flex: 1; }
.center-card__name { font-size: 13pt; font-weight: 800; margin: 0; }
.center-card__group { font-size: 9pt; margin: 1mm 0 0; color: #333; }
.center-card__phone { font-size: 9pt; margin: 1mm 0 0; color: #555; }
</style>
