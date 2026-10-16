import type { MediaAsset } from '~/types/manhagy'

interface UploadInstructions { method: 'chunks' | 'put', url: string, headers: Record<string, string>, chunk_bytes: number | null }

/**
 * Uploading to the library. A video goes to منهجي's server in chunks (resumable: a dropped chunk is
 * retried from what the server says it has), then the server encodes it; a PDF goes in one request.
 * `progress` is 0–100.
 */
export function useMediaUpload() {
  const api = useApi()
  const progress = ref(0)
  const uploading = ref(false)

  function put(url: string, headers: Record<string, string>, body: Blob, onProgress?: (loaded: number) => void): Promise<void> {
    return new Promise((resolve, reject) => {
      const xhr = new XMLHttpRequest()
      xhr.open('PUT', url)
      for (const [k, v] of Object.entries(headers)) xhr.setRequestHeader(k, v)
      xhr.upload.onprogress = e => onProgress?.(e.loaded)
      xhr.onload = () => (xhr.status < 400 ? resolve() : reject(new Error(`upload ${xhr.status}`)))
      xhr.onerror = () => reject(new Error('upload failed'))
      xhr.send(body)
    })
  }

  /** Every chunk from what the server has; a failed one is retried (waiting longer each time). */
  async function sendChunks(upload: UploadInstructions, file: File) {
    const size = upload.chunk_bytes ?? 8 * 1024 * 1024
    const token = useAuthToken().token.value
    const headers = { ...upload.headers, 'Content-Type': 'application/octet-stream', 'Accept': 'application/json', ...(token ? { Authorization: `Bearer ${token}` } : {}) }
    let offset = 0
    let failures = 0
    while (offset < file.size) {
      const chunk = file.slice(offset, offset + size)
      try {
        await put(`${upload.url}?offset=${offset}`, headers, chunk, loaded => (progress.value = Math.min(99, Math.round((offset + loaded) / file.size * 100))))
        offset += chunk.size
        failures = 0
      }
      catch (e) {
        if (++failures > 8) throw e
        await new Promise(r => setTimeout(r, Math.min(30_000, 1000 * 2 ** failures)))
        // Continue from what really arrived.
        offset = (await api<{ data: { received: number } }>(upload.url.replace(/^.*\/api\/v1/, ''))).data.received
      }
    }
  }

  async function upload(file: File, title: string): Promise<MediaAsset> {
    uploading.value = true
    progress.value = 0
    try {
      if (file.type === 'application/pdf') {
        const body = new FormData()
        body.append('file', file)
        body.append('title', title)
        const res = await api<{ data: MediaAsset }>('/media/files', { method: 'POST', body })
        progress.value = 100
        return res.data
      }
      const created = await api<{ data: MediaAsset & { upload: UploadInstructions } }>('/media/videos', { method: 'POST', body: { title, size_bytes: file.size } })
      const instructions = created.data.upload
      if (instructions.method === 'chunks') {
        await sendChunks(instructions, file)
      }
      // The development host has no real upload address.
      else if (!new URL(instructions.url).hostname.endsWith('.test')) {
        await put(instructions.url, instructions.headers, file, loaded => (progress.value = Math.round(loaded / file.size * 100)))
      }
      progress.value = 100
      return (await api<{ data: MediaAsset }>(`/media/${created.data.id}/check`, { method: 'POST' })).data
    }
    finally {
      uploading.value = false
    }
  }

  return { upload, progress, uploading }
}
