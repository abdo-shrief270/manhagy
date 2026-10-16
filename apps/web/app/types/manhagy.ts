/** API shapes of منهجي's own modules (the kernel's are in api.ts). Money is piasters. */

export interface Learner {
  id: string // the profile (this account's row)
  learner_id: string
  name: string
  phone: string
  parent_phone: string | null
  grade_id: string | null
  grade: string | null
  devices: number
  blocked: boolean
  joined_at: string
  last_seen_at: string | null
}

export interface LearnerDetail extends Learner {
  notes?: string | null
  blocked_reason?: string | null
  devices_list?: never
  device_items?: never
}

export interface CourseSummary {
  id: string
  title: string
  description: string | null
  price: number
  access_days: number | null
  status: 'draft' | 'published' | 'archived'
  sort: number
  grade_node_id: string
  grade: string | null
  lessons_count: number
  free_previews: number
  published_at: string | null
}

export type LessonKind = 'video' | 'pdf' | 'exam'

export interface Lesson {
  id: string
  course_id: string
  chapter_id: string
  kind: LessonKind
  title: string
  media_id: string | null
  duration_seconds: number | null
  free_preview: boolean
  publish_at: string | null
  requires_previous: boolean
  max_views: number | null
  count_percent: number
  sort: number
  media?: { title: string, status: MediaStatus } | null
}

export interface Chapter {
  id: string
  title: string
  lessons: Lesson[]
}

export interface CourseDetail extends CourseSummary {
  chapters: Chapter[]
}

export type MediaStatus = 'uploading' | 'processing' | 'ready' | 'failed'

export interface MediaAsset {
  id: string
  kind: 'video' | 'file'
  title: string
  status: MediaStatus
  duration_seconds: number | null
  size_bytes: number | null
  created_at: string
}

export interface CurriculumNode {
  id: string
  parent_id: string | null
  kind: string
  kind_label: string
  name: string
  code: string | null
  has_children: boolean
  teachable: boolean
  label?: string
}

export interface Enrollment {
  id: string
  learner_id: string
  course_id: string
  source: 'manual' | 'code' | 'wallet' | 'free'
  starts_at: string
  ends_at: string | null
  active: boolean
  revoked_at: string | null
  revoked_reason: string | null
  granted_by_name: string | null
  created_at: string
  learner_name?: string | null
  course_title?: string | null
}

export interface CourseProgress {
  course_id: string
  title: string
  active: boolean
  lessons: { id: string, title: string, kind: LessonKind, views: number, max_views: number | null, extra_views: number, watched_seconds: number, completed_at: string | null, last_viewed_at: string | null }[]
}

export interface WalletTransaction {
  id: string
  type: 'code' | 'transfer' | 'purchase' | 'adjustment'
  amount: number
  balance_after: number
  note: string | null
  by_name: string | null
  created_at: string
}

export interface WalletTopup {
  id: string
  learner_id: string
  amount: number
  method: 'instapay' | 'wallet' | 'other'
  reference: string | null
  has_proof: boolean
  status: 'pending' | 'approved' | 'rejected'
  approved_amount: number | null
  reason: string | null
  decided_by_name: string | null
  decided_at: string | null
  created_at: string
  learner_name?: string | null
  learner_phone?: string | null
}

export interface WalletSettings {
  transfers_enabled: boolean
  instapay: string | null
  wallet_number: string | null
  instructions: string | null
  min_topup: number
}

export interface CodeBatch {
  id: string
  number: string
  label: string | null
  kind: 'course' | 'balance'
  course_id: string | null
  course_title: string | null
  days: number | null
  value: number
  unit_price: number
  count: number
  expires_at: string | null
  distributor_id: string | null
  distributor_name: string | null
  status: 'active' | 'cancelled'
  cancelled_reason: string | null
  created_by_name: string | null
  created_at: string
  stats: { unused: number, used: number, cancelled: number }
  codes?: { serial: number, code: string }[]
  used?: { serial: number, learner_id: string | null, learner_name: string | null, used_at: string | null }[]
  cancelled_serials?: number[]
}

export interface DistributorStats {
  given: number
  used: number
  unused: number
  cancelled: number
  sold: number
  collected: number
  due: number
}

export interface Distributor {
  id: string
  name: string
  phone: string | null
  kind: 'library' | 'center' | 'assistant' | 'other'
  notes: string | null
  active: boolean
  stats?: DistributorStats
}

export type QuestionKind = 'mcq' | 'true_false' | 'fill' | 'essay' | 'photo'

export interface Question {
  id: string
  course_id: string
  chapter_id: string | null
  kind: QuestionKind
  body: string
  options: { id: string, text: string }[] | null
  answer: string | boolean | string[] | null
  explanation: string | null
  points: number
  difficulty: 'easy' | 'medium' | 'hard'
  active: boolean
}

export interface ExamSettings {
  duration_minutes: number
  opens_at: string | null
  closes_at: string | null
  attempts_allowed: number
  shuffle_questions: boolean
  shuffle_options: boolean
  pass_percent: number
  show_answers: 'after_submit' | 'after_close' | 'never'
  attempts: number
}

export interface ExamDetail {
  lesson_id: string
  course_id: string
  title: string
  exam: ExamSettings | null
  questions: Question[]
}

export interface ExamResults {
  summary: { attempts: number, in_progress: number, average_percent: number | null, pass_percent: number | null }
  attempts: { id: string, learner_id: string, learner_name: string | null, number: number, status: string, score: number | null, max_score: number, percent: number | null, passed: boolean, leaves: number, started_at: string, submitted_at: string | null, submitted_by: string | null }[]
  questions: { id: string, body: string, answered: number, correct_percent: number | null }[]
}

export interface GradingItem {
  id: string
  kind: 'essay' | 'photo'
  question: string
  model_answer: string | null
  answer: string | null
  has_photo: boolean
  max_points: number
  points_awarded: number | null
  comment: string | null
  learner_name: string | null
  exam: string | null
  submitted_at: string
  graded_by_name: string | null
}

export interface LessonQuestion {
  id: string
  lesson_id: string
  body: string
  position: number | null
  status: 'open' | 'answered' | 'hidden'
  answer: string | null
  public: boolean
  answered_by_name: string | null
  answered_at: string | null
  created_at: string
  learner_id?: string
  learner_name?: string | null
  lesson_title?: string | null
  course_id?: string | null
}

export interface Dashboard {
  money?: Record<'today' | 'month', { codes: number, codes_count: number, transfers: number, total: number }>
  students: { total: number, new_today: number, new_month: number, active_week: number, enrolled: number }
  watching: { views_today: number, views_month: number, hours_month: number, watching_now: number, top_lessons: { lesson_id: string, title: string, course: string, views: number }[] }
  exams: { attempts_week: number, average_percent_week: number | null, in_progress: number }
  waiting: { transfers?: number }
  sharing: { learner_id: string, name: string | null, networks: number, devices: number, sessions: number }[]
}

export interface ParentReportRow {
  learner_id: string
  parent_phone: string
  student: string
  watched_minutes: number
  missing: number
  message: Record<string, string | null>
}

export interface Site {
  tenant_id: string
  slug: string
  title: string
  tagline: string | null
  color: string
  device_limit: number
  registration_open: boolean
  require_parent_phone: boolean
  url: string | null
  colors?: string[]
}
