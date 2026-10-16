import type { MediaAsset } from '~/types/manhagy'

/**
 * Uploading to the library: a video goes straight from the browser to the video provider (the
 * API only hands out the upload address), a PDF goes to the API. `progress` is 0–100.
 */
export function useMediaUpload() {
  const api = useApi()
  const progress = ref(0)
  const uploading = ref(false)

  function put(url: string, headers: Record<string, string>, file: File): Promise<void> {
    return new Promise((resolve, reject) => {
      const xhr = new XMLHttpRequest()
      xhr.open('PUT', url)
      for (const [k, v] of Object.entries(headers)) xhr.setRequestHeader(k, v)
      xhr.upload.onprogress = e => e.lengthComputable && (progress.value = Math.round(e.loaded / e.total * 100))
      xhr.onload = () => (xhr.status < 400 ? resolve() : reject(new Error(`upload ${xhr.status}`)))
      xhr.onerror = () => reject(new Error('upload failed'))
      xhr.send(file)
    })
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
      const created = await api<{ data: MediaAsset & { upload: { url: string, headers: Record<string, string> } } }>('/media/videos', { method: 'POST', body: { title, size_bytes: file.size } })
      // The development provider has no real upload address.
      if (!new URL(created.data.upload.url).hostname.endsWith('.test')) {
        await put(created.data.upload.url, created.data.upload.headers, file)
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
