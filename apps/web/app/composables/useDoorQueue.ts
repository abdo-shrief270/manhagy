export interface DoorScan {
  id: string
  session_id: string
  token?: string
  learner_id?: string
  scanned_at: string
  pay?: { id: string, kind: 'session' | 'month', amount: number }
}

export interface DoorResult { id: string, status: string, name?: string, owes?: number }

/**
 * The door never waits for the internet: every scan goes into a queue on this device (localStorage)
 * and is sent in order; the ids make a resend harmless. Sent every few seconds and when back online.
 */
export function useDoorQueue() {
  const store = useSessionStore()
  const api = useApi()
  const key = computed(() => `manhagy:door-queue:${store.session?.tenant.id ?? ''}`)
  const queue = useState<DoorScan[]>('door-queue', () => [])
  const sending = ref(false)
  const lastError = ref<string | null>(null)

  function load() {
    try {
      queue.value = JSON.parse(localStorage.getItem(key.value) ?? '[]')
    }
    catch {
      queue.value = []
    }
  }
  function save() {
    try {
      localStorage.setItem(key.value, JSON.stringify(queue.value))
    }
    catch {
      // Storage full / blocked: the queue still lives in memory until sent.
    }
  }

  function push(scan: DoorScan) {
    queue.value = [...queue.value, scan]
    save()
    flush()
  }

  async function flush(): Promise<DoorResult[]> {
    if (sending.value || !queue.value.length || !navigator.onLine) return []
    sending.value = true
    const batch = queue.value.slice(0, 100)
    try {
      const res = await api<{ data: DoorResult[] }>('/center/scans', { method: 'POST', body: { scans: batch } })
      const sent = new Set(batch.map(s => s.id))
      queue.value = queue.value.filter(s => !sent.has(s.id))
      save()
      lastError.value = null
      return res.data
    }
    catch (e) {
      lastError.value = isNetworkError(e) ? null : apiErrorMessage(e)
      return []
    }
    finally {
      sending.value = false
    }
  }

  let timer: ReturnType<typeof setInterval> | undefined
  onMounted(() => {
    load()
    flush()
    timer = setInterval(flush, 5000)
    window.addEventListener('online', flush)
  })
  onBeforeUnmount(() => {
    clearInterval(timer)
    window.removeEventListener('online', flush)
  })

  return { queue, sending, lastError, push, flush }
}
