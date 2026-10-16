export const KINDS = { school: 'مدارس', university: 'جامعة', skills: 'مهارات' } as const

/** Piasters → «250 ج». */
export function formatMoney(piasters: number): string {
  if (piasters === 0) return 'مجاني'
  return `${new Intl.NumberFormat('ar-EG-u-nu-latn', { maximumFractionDigits: 2 }).format(piasters / 100)} ج`
}

export function formatDate(iso: string | null | undefined): string {
  return iso ? new Intl.DateTimeFormat('ar-EG-u-nu-latn', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'Africa/Cairo' }).format(new Date(iso)) : ''
}

export function formatNumber(n: number): string {
  return new Intl.NumberFormat('ar-EG-u-nu-latn').format(n)
}

export function duration(seconds: number | null): string {
  if (!seconds) return ''
  const m = Math.round(seconds / 60)
  return m < 60 ? `${m} د` : `${Math.floor(m / 60)} س ${m % 60 ? `${m % 60} د` : ''}`.trim()
}

export function accessText(days: number | null): string {
  if (days === null) return 'لحد ما الكورس يخلص'
  if (days === 30) return 'شهر'
  if (days % 30 === 0) return `${days / 30} شهور`
  return `${days} يوم`
}

/** The teacher's colour as a CSS colour (the platform's palette). */
export const TEACHER_COLORS: Record<string, string> = {
  indigo: '#4f46e5', rose: '#e11d48', teal: '#0d9488', amber: '#d97706',
  sky: '#0284c7', violet: '#7c3aed', emerald: '#059669', slate: '#334155',
}

export function teacherColor(color: string | null): string {
  return TEACHER_COLORS[color ?? ''] ?? TEACHER_COLORS.indigo!
}

/** «ثانوي عام › الصف التالت الثانوي › علمي علوم › فيزياء» → its last two levels. */
export function shortSubject(label: string): string {
  return label.split(' › ').slice(-2).join(' · ')
}
