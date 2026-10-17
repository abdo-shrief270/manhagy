/**
 * Broadcasting from the browser to the live server with WHIP (WebRTC-HTTP ingestion): the camera or
 * the screen, with the microphone. H.264 first, so students' players (HLS) can play it as is.
 */
export function useWhip() {
  const state = ref<'idle' | 'connecting' | 'live' | 'failed'>('idle')
  const error = ref<string | null>(null)
  let pc: RTCPeerConnection | null = null
  let resource: string | null = null

  function preferH264(transceiver: RTCRtpTransceiver) {
    const caps = RTCRtpSender.getCapabilities('video')?.codecs ?? []
    const h264 = caps.filter(c => c.mimeType.toLowerCase() === 'video/h264')
    if (h264.length && 'setCodecPreferences' in transceiver) {
      transceiver.setCodecPreferences([...h264, ...caps.filter(c => c.mimeType.toLowerCase() !== 'video/h264')])
    }
  }

  function gathered(peer: RTCPeerConnection): Promise<void> {
    if (peer.iceGatheringState === 'complete') return Promise.resolve()
    return new Promise((resolve) => {
      const done = () => peer.iceGatheringState === 'complete' && resolve()
      peer.addEventListener('icegatheringstatechange', done)
      setTimeout(resolve, 2500) // enough candidates by now
    })
  }

  async function start(url: string, stream: MediaStream, maxKbps = 2500) {
    await stop()
    state.value = 'connecting'
    error.value = null
    try {
      pc = new RTCPeerConnection({ iceServers: [{ urls: 'stun:stun.l.google.com:19302' }] })
      for (const track of stream.getTracks()) {
        const t = pc.addTransceiver(track, { direction: 'sendonly', streams: [stream] })
        if (track.kind === 'video') {
          preferH264(t)
          const params = t.sender.getParameters()
          params.encodings = [{ maxBitrate: maxKbps * 1000 }]
          await t.sender.setParameters(params).catch(() => {})
        }
      }
      pc.addEventListener('connectionstatechange', () => {
        if (pc?.connectionState === 'connected') state.value = 'live'
        if (pc?.connectionState === 'failed') {
          state.value = 'failed'
          error.value = 'الاتصال بسيرفر البث وقع. جرّب تاني.'
        }
      })
      await pc.setLocalDescription(await pc.createOffer())
      await gathered(pc)
      const res = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/sdp' }, body: pc.localDescription?.sdp })
      if (!res.ok) {
        throw new Error(res.status === 401 ? 'سيرفر البث رفض المفتاح (يمكن الحصة خلصت أو المفتاح اتغيّر).' : `سيرفر البث ردّ ${res.status}`)
      }
      const location = res.headers.get('Location')
      resource = location ? new URL(location, url).toString() : null
      await pc.setRemoteDescription({ type: 'answer', sdp: await res.text() })
    }
    catch (e) {
      state.value = 'failed'
      error.value = e instanceof Error ? e.message : 'البث ما اشتغلش.'
      pc?.close()
      pc = null
    }
  }

  async function stop() {
    if (resource) await fetch(resource, { method: 'DELETE' }).catch(() => {})
    resource = null
    pc?.close()
    pc = null
    state.value = 'idle'
  }

  /** Switching camera ⇄ screen without reconnecting. */
  async function replaceVideo(track: MediaStreamTrack) {
    const sender = pc?.getSenders().find(s => s.track?.kind === 'video')
    await sender?.replaceTrack(track)
  }

  return { state, error, start, stop, replaceVideo }
}
