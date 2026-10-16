export const DAY_NAMES = ['الأحد', 'الاتنين', 'التلات', 'الأربع', 'الخميس', 'الجمعة', 'السبت']

/** A random id made on this device (the door's scans and payments are saved once by it). */
export function deviceUuid(): string {
  return crypto.randomUUID()
}

/** «16:00» → «4:00 م». */
export function clockLabel(time: string): string {
  const [h = 0, m = 0] = time.split(':').map(Number)
  const d = new Date(2000, 0, 1, h, m)
  return d.toLocaleTimeString('ar-EG-u-nu-latn', { hour: 'numeric', minute: '2-digit' })
}
