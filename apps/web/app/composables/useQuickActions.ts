export interface QuickAction {
  label: string
  description: string
  icon: string
  permission: string
  feature?: string
  /** a page to open… */
  to?: string
  /** …or something to do in place (open a window) */
  run?: () => void
  /** shown as a hint on the tile */
  kbd?: string
}

/**
 * The everyday "do something now" shortcuts, shown on the home page and in Ctrl+K.
 * `can()` already folds in whether the account can use the permission's module.
 */
export function useQuickActions() {
  const store = useSessionStore()

  const actions: QuickAction[] = [
    { to: '/courses?new=1', label: 'كورس جديد', description: 'كورس على مادة من المنهج', icon: 'i-lucide-book-plus', permission: 'courses.manage' },
    { to: '/media', label: 'ارفع فيديو', description: 'فيديو أو ملزمة PDF لمكتبتك', icon: 'i-lucide-upload', permission: 'media.upload' },
    { to: '/codes/new', label: 'دفعة أكواد', description: 'كروت لكورس أو رصيد', icon: 'i-lucide-ticket', permission: 'codes.manage' },
    { to: '/wallet/topups', label: 'تحويلات الشحن', description: 'أكّد تحويلات الطلاب', icon: 'i-lucide-wallet', permission: 'wallet.confirm' },
    { to: '/grading', label: 'التصحيح', description: 'الإجابات المقالية والصور', icon: 'i-lucide-pen-line', permission: 'assessments.grade' },
    { to: '/questions', label: 'أسئلة الطلاب', description: 'رد على اللي سألوا تحت الدروس', icon: 'i-lucide-message-circle-question', permission: 'engagement.answer' },
    { to: '/parent-reports', label: 'تقرير ولي الأمر', description: 'ابعت تقرير الأسبوع على واتساب', icon: 'i-lucide-send', permission: 'reports.parents' },
    { to: '/learners', label: 'الطلاب', description: 'دوّر على طالب، فك جهاز، اشتراك بالإيد', icon: 'i-lucide-users-round', permission: 'learners.view' },
    { to: '/codes?lookup=1', label: 'استعلام عن كود', description: 'الكود ده اتستخدم ولا لأ', icon: 'i-lucide-search-check', permission: 'codes.view' },
    { to: '/settings/site', label: 'منصتي', description: 'اللينك والاسم والألوان والأجهزة', icon: 'i-lucide-globe', permission: 'site.manage' },
  ]

  return computed(() => actions.filter(a => store.can(a.permission) && (!a.feature || store.hasFeature(a.feature))))
}
