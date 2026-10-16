export interface MessageTemplate {
  key: string
  label: string
  group: string
  group_label: string
  variables: { name: string, label: string }[]
  body: string
  default_body: string
  customized: boolean
  updated_by_name: string | null
  /** The owner's switch this message belongs to (off = it isn't offered anywhere) */
  feature?: string | null
}

export type MessageVars = Record<string, string | null | undefined>

/** What a message is about (the API's MessageController::SUBJECTS), for its history. */
export type MessageSubject = { type: 'learner' | 'guardian' | 'enrollment' | 'exam_attempt' | 'group_session', id: string }

/**
 * Fills a template: {name} → its value. A line with a variable that has no value is left out
 * (no «📝 » with nothing after it); names the message doesn't know stay as typed.
 */
export function renderTemplate(body: string, vars: MessageVars): string {
  return body
    .split('\n')
    .filter(line => [...line.matchAll(/\{(\w+)\}/g)].every(m => !(m[1]! in vars) || !!vars[m[1]!]))
    .map(line => line.replace(/\{(\w+)\}/g, (all, name: string) => name in vars ? (vars[name] ?? '') : all))
    .join('\n')
}

/**
 * WhatsApp messages in the account's own wording (/settings/templates). Each one opens WhatsApp
 * with the text (no Meta API: the teacher's own WhatsApp sends it) and is logged.
 */
export function useMessages() {
  const api = useApi()
  const store = useSessionStore()
  const templates = useState<MessageTemplate[] | null>('message-templates', () => null)

  async function load(force = false) {
    if (templates.value && !force) {
      return templates.value
    }
    try {
      templates.value = (await api<{ data: MessageTemplate[] }>('/messages/templates')).data
    }
    catch {
      // Not allowed / offline: send() falls back to the given text.
    }
    return templates.value
  }
  load()

  /** The account's wording for this template, filled. */
  function text(key: string, vars: MessageVars): string {
    const template = templates.value?.find(t => t.key === key)
    return template ? renderTemplate(template.body, vars) : Object.values(vars).filter(Boolean).join('\n')
  }

  function send(key: string, phone: string | null, vars: MessageVars, subject?: MessageSubject) {
    const message = text(key, { teacher: store.session?.tenant.name, ...vars })
    window.open(phone ? whatsappLink(phone, message) : `https://wa.me/?text=${encodeURIComponent(message)}`, '_blank')
    if (store.can('messages.send')) {
      api('/messages/log', { method: 'POST', body: { template: key, phone, subject_type: subject?.type, subject_id: subject?.id } }).catch(() => {})
    }
  }

  return { templates, load, text, send }
}
