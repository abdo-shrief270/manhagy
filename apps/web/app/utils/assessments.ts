import type { Question, QuestionKind } from '~/types/manhagy'

export const QUESTION_KINDS: Record<QuestionKind, { label: string, icon: string, manual?: boolean }> = {
  mcq: { label: 'اختيار من متعدد', icon: 'i-lucide-list' },
  true_false: { label: 'صح وغلط', icon: 'i-lucide-check-check' },
  fill: { label: 'أكمل', icon: 'i-lucide-text-cursor-input' },
  essay: { label: 'مقالي', icon: 'i-lucide-align-right', manual: true },
  photo: { label: 'صورة الحل', icon: 'i-lucide-camera', manual: true },
}

export const DIFFICULTY: Record<Question['difficulty'], string> = { easy: 'سهل', medium: 'متوسط', hard: 'صعب' }

/** The right answer in words, for the bank list. */
export function answerText(q: Question): string {
  if (q.kind === 'mcq') return q.options?.find(o => o.id === q.answer)?.text ?? '—'
  if (q.kind === 'true_false') return q.answer ? 'صح' : 'غلط'
  if (q.kind === 'fill') return (q.answer as string[] | null)?.join(' / ') ?? '—'
  return 'بيتصحّح بالإيد'
}
