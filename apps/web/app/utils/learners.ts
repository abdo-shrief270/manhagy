import type { Enrollment } from '~/types/manhagy'

export const ENROLLMENT_SOURCES: Record<Enrollment['source'], string> = {
  manual: 'بالإيد',
  code: 'بكود',
  wallet: 'من المحفظة',
  free: 'مجاني',
}
