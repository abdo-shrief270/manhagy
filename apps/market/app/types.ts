export type Kind = 'school' | 'university' | 'skills'

export interface Teacher {
  name: string
  slug: string
  headline: string | null
  color: string | null
}

export interface CourseCard {
  id: string
  title: string
  description: string | null
  price: number
  access_days: number | null
  subject: string
  kind: Kind
  lessons_count: number
  free_previews: number
  students: number
  rating: number | null
  reviews: number
  published_at: string | null
  teacher: Teacher
  url: string
}

export interface Review {
  id: string
  first_name: string
  rating: number
  comment: string | null
  at: string
}

export interface CourseDetail extends CourseCard {
  syllabus: { title: string, lessons: { title: string, kind: 'video' | 'pdf' | 'exam', duration_seconds: number | null, free_preview: boolean }[] }[]
  reviews_list: Review[]
  distribution: Record<string, number>
  more_from_teacher: CourseCard[]
}

export interface TeacherPage extends Teacher {
  about: string | null
  site_url: string
  courses_count: number
  students: number
  reviews: number
  rating: number | null
  courses: CourseCard[]
}

export interface Node {
  id: string
  name: string
  kind: string
  kind_label: string
  has_children: boolean
  courses: number
}

export interface Home {
  kinds: Record<Kind, number>
  courses: number
  teachers: number
  top_rated: CourseCard[]
  newest: CourseCard[]
  popular: CourseCard[]
}

export interface Certificate {
  code: string
  display_code: string
  title: string
  learner_name: string
  course_title: string
  teacher_name: string
  minutes: number
  exam_percent: number | null
  issued_at: string
  revoked: boolean
  url: string
  site: { title: string, url: string, color: string }
}
