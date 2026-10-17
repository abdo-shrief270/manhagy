import type { CourseSummary, LessonKind } from '~/types/manhagy'

export const COURSE_STATUS: Record<CourseSummary['status'], { label: string, color: 'success' | 'neutral' | 'warning' }> = {
  published: { label: 'منشور', color: 'success' },
  draft: { label: 'مسودة', color: 'warning' },
  archived: { label: 'مقفول', color: 'neutral' },
}

export const LESSON_KINDS: Record<LessonKind, { label: string, icon: string }> = {
  video: { label: 'فيديو', icon: 'i-lucide-play' },
  pdf: { label: 'ملزمة PDF', icon: 'i-lucide-file-text' },
  exam: { label: 'امتحان', icon: 'i-lucide-list-checks' },
  live: { label: 'لايف', icon: 'i-lucide-radio' },
}

/** 754 → «12:34», 3725 → «1:02:05». */
export function formatDuration(seconds: number | null | undefined): string {
  if (!seconds) return '—'
  const h = Math.floor(seconds / 3600)
  const m = Math.floor((seconds % 3600) / 60)
  const s = seconds % 60
  const pad = (n: number) => String(n).padStart(2, '0')
  return h ? `${h}:${pad(m)}:${pad(s)}` : `${m}:${pad(s)}`
}

/** 30 → «شهر»، 7 → «أسبوع»، null → «لحد ما الكورس يقفل». */
export function accessLabel(days: number | null): string {
  if (days === null) return 'لحد ما الكورس يقفل'
  if (days === 30) return 'شهر'
  if (days === 7) return 'أسبوع'
  if (days % 30 === 0) return `${days / 30} شهور`
  return `${days} يوم`
}
