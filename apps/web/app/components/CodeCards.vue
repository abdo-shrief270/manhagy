<template>
  <PrintSheet page-size="A4" margin="8mm">
    <div class="code-cards">
      <div v-for="c in codes" :key="c.serial" class="code-card">
        <div class="code-card__head">
          <strong>{{ title }}</strong>
          <span class="num">#{{ c.serial }}</span>
        </div>
        <p class="code-card__what">
          {{ what }}
        </p>
        <div class="code-card__body">
          <PrintQrCode :value="`${siteUrl}/redeem?code=${c.code}`" :size="70" />
          <div>
            <p class="code-card__code num" dir="ltr">
              {{ c.code }}
            </p>
            <p class="code-card__how">
              ادخل على <span class="num" dir="ltr">{{ siteHost }}</span> ← «عندي كود»، أو صوّر الـ QR
            </p>
          </div>
        </div>
        <p v-if="expires" class="code-card__foot">
          صالح لحد {{ expires }} · بيتستخدم مرة واحدة
        </p>
        <p v-else class="code-card__foot">
          بيتستخدم مرة واحدة
        </p>
      </div>
    </div>
  </PrintSheet>
</template>

<script setup lang="ts">
/** A4 sheets of 10 cards (2 × 5): the platform's name, what the code opens, the code and a QR to the redeem page. */
defineProps<{ codes: { serial: number, code: string }[], title: string, what: string, siteUrl: string, siteHost: string, expires?: string | null }>()
</script>

<style>
.code-cards { display: grid; grid-template-columns: 1fr 1fr; gap: 4mm; font-family: Cairo, sans-serif; color: #000; }
.code-card { border: 1px dashed #999; border-radius: 3mm; padding: 3mm 4mm; height: 52mm; display: flex; flex-direction: column; gap: 1.5mm; break-inside: avoid; }
.code-card__head { display: flex; justify-content: space-between; font-size: 11pt; }
.code-card__what { font-size: 9pt; color: #333; margin: 0; }
.code-card__body { display: flex; gap: 3mm; align-items: center; flex: 1; }
.code-card__code { font-size: 15pt; font-weight: 800; letter-spacing: .5mm; margin: 0; }
.code-card__how { font-size: 7.5pt; color: #444; margin: 1mm 0 0; }
.code-card__foot { font-size: 7.5pt; color: #555; margin: 0; }
</style>
