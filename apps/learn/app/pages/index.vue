<template>
  <div class="space-y-8">
    <template v-if="!learner.me.value">
      <section class="app-card overflow-hidden bg-gradient-to-l from-primary/10 to-transparent p-6 sm:p-10">
        <h1 class="text-2xl font-extrabold sm:text-3xl">
          {{ site?.title }}
        </h1>
        <p v-if="site?.tagline" class="mt-1 text-(--ui-text-muted)">
          {{ site.tagline }}
        </p>
        <div class="mt-5 flex flex-wrap gap-2">
          <UButton v-if="site?.registration_open" to="/register" size="lg" icon="i-lucide-user-plus" label="سجّل حساب" />
          <UButton to="/login" size="lg" color="neutral" variant="outline" label="عندي حساب" />
        </div>
      </section>
      <section v-if="publicCourses?.length" class="space-y-3">
        <h2 class="text-lg font-bold">
          الكورسات
        </h2>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <CourseTile v-for="c in publicCourses" :key="c.id" :course="c" />
        </div>
      </section>
    </template>

    <template v-else>
      <div>
        <h1 class="text-2xl font-extrabold">
          أهلاً {{ learner.me.value.name.split(' ')[0] }} 👋
        </h1>
        <p class="text-(--ui-text-muted)">
          كمّل من حيث وقفت.
        </p>
      </div>
      <section class="space-y-3">
        <h2 class="text-lg font-bold">
          كورساتي
        </h2>
        <div v-if="mine.length" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <CourseTile v-for="c in mine" :key="c.id" :course="c" />
        </div>
        <div v-else class="app-card space-y-3 p-6 text-center">
          <p class="text-(--ui-text-muted)">
            لسه مش مشترك في كورس. اشترك بكود من المكتبة أو من محفظتك.
          </p>
          <UButton to="/redeem" icon="i-lucide-ticket" label="عندي كود" />
        </div>
      </section>
      <section v-if="others.length" class="space-y-3">
        <h2 class="text-lg font-bold">
          كورسات تانية
        </h2>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <CourseTile v-for="c in others" :key="c.id" :course="c" />
        </div>
      </section>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { CourseCard } from '~/utils/types'

const { site, slug } = useSite()
const learner = useLearner()
const api = useLearnApi()

const { data: publicCourses } = await useAsyncData('public-courses', async () => learner.me.value ? [] : (await api<{ data: CourseCard[] }>(`/public/sites/${slug.value}/courses`)).data)
const { data: courses } = await useAsyncData('my-courses', async () => learner.me.value ? (await api<{ data: CourseCard[] }>('/learner/courses')).data : [], { watch: [() => learner.me.value?.name] })
const mine = computed(() => (courses.value ?? []).filter(c => c.enrolled))
const others = computed(() => (courses.value ?? []).filter(c => !c.enrolled))
</script>
