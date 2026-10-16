<template>
  <div class="mx-auto max-w-xl space-y-6">
    <div v-if="!cert" class="card space-y-3 p-8 text-center">
      <p class="text-4xl" aria-hidden="true">
        ✕
      </p>
      <h1 class="text-xl font-extrabold">
        مفيش شهادة بالكود ده
      </h1>
      <p class="text-muted">
        اتأكد من الكود المكتوب على الشهادة.
      </p>
      <NuxtLink to="/verify" class="btn-ghost">
        جرّب كود تاني
      </NuxtLink>
    </div>
    <div v-else class="card space-y-4 p-6">
      <p class="rounded-xl px-4 py-3 text-center font-bold" :class="cert.revoked ? 'bg-bad/10 text-bad' : 'bg-ok/10 text-ok'">
        {{ cert.revoked ? 'الشهادة دي اتلغت — مش معتمدة' : 'شهادة سليمة ✓' }}
      </p>
      <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
        <dt class="text-muted">
          الاسم
        </dt>
        <dd class="font-bold">
          {{ cert.learner_name }}
        </dd>
        <dt class="text-muted">
          الكورس
        </dt>
        <dd class="font-bold">
          {{ cert.course_title }}
        </dd>
        <dt class="text-muted">
          من
        </dt>
        <dd>{{ cert.teacher_name }}</dd>
        <dt class="text-muted">
          التاريخ
        </dt>
        <dd class="num">
          {{ formatDate(cert.issued_at) }}
        </dd>
        <template v-if="cert.exam_percent !== null">
          <dt class="text-muted">
            الامتحانات
          </dt>
          <dd class="num">
            {{ cert.exam_percent }}%
          </dd>
        </template>
        <dt class="text-muted">
          الكود
        </dt>
        <dd class="num font-mono" dir="ltr">
          {{ cert.display_code }}
        </dd>
      </dl>
      <a :href="cert.url" class="btn-ghost w-full">شوف الشهادة</a>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { Certificate } from '~/types'

const code = String(useRoute().params.code)
const { data: cert } = await useAsyncData(`cert-${code}`, () => marketFetch<{ data: Certificate }>(`/certificates/${encodeURIComponent(code)}`).then(r => r.data).catch(() => null))
useSeoMeta({ title: 'التحقق من شهادة', robots: 'noindex' })
</script>
