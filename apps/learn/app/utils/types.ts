export interface SitePublic {
  slug: string
  title: string
  tagline: string | null
  color: string
  teacher: string
  registration_open: boolean
  require_parent_phone: boolean
}

export interface Me {
  name: string
  phone: string
  phone_verified: boolean
  grade_id: string | null
  grade: string | null
  parent_phone: string | null
  devices: { id: string, name: string, last_seen_at: string | null, current: boolean }[]
}

export interface CourseCard {
  id: string
  title: string
  description: string | null
  price: number
  access_days: number | null
  grade: string | null
  lessons_count: number
  free_previews: number
  enrolled?: boolean
  ends_at?: string | null
  completed_lessons?: number
}

export type LockReason = 'not_yet' | 'not_enrolled' | 'previous_required' | 'views_used_up' | null

export interface OutlineLesson {
  id: string
  kind: 'video' | 'pdf' | 'exam'
  title: string
  duration_seconds: number | null
  free_preview: boolean
  publish_at: string | null
  locked: LockReason
  views_left: number | null
  completed: boolean
  last_position: number
}

export interface CourseOutline extends CourseCard {
  enrolled: boolean
  ends_at: string | null
  chapters: { id: string, title: string, lessons: OutlineLesson[] }[]
}

export interface Verification {
  code: string
  text: string
  whatsapp: string
  link: string
  expires_at: string
}

export const LOCK_TEXT: Record<Exclude<LockReason, null>, string> = {
  not_yet: 'لسه ما اتفتحش',
  not_enrolled: 'اشترك عشان تفتحه',
  previous_required: 'خلّص اللي قبله الأول',
  views_used_up: 'خلصت مرات المشاهدة',
}

/** 754 → «12:34». */
export function formatDuration(seconds: number | null | undefined): string {
  if (!seconds) return ''
  const h = Math.floor(seconds / 3600)
  const m = Math.floor((seconds % 3600) / 60)
  const s = seconds % 60
  const pad = (n: number) => String(n).padStart(2, '0')
  return h ? `${h}:${pad(m)}:${pad(s)}` : `${m}:${pad(s)}`
}
