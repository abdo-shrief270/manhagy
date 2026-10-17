import { ConnectionState, DisconnectReason, Room, RoomEvent, Track, VideoPresets, type Participant } from 'livekit-client'

/** A track on the stage: whose it is and what it shows. */
export interface LiveTrack {
  key: string
  identity: string
  name: string
  teacher: boolean
  local: boolean
  source: Track.Source
  track: Track
  muted: boolean
}

/** What the server says (topic `manhagy`): feed / moderated / poll / hands / ended — look again. */
type Signal = (type: string) => void

/**
 * One LiveKit room (WebRTC, the delay of a call): connect with the token the API gave, publish the
 * microphone / camera / screen when allowed, and the tracks to show. Simulcast + adaptive stream:
 * each viewer gets the quality its connection and its tile need.
 */
export function useLiveRoom(onSignal: Signal = () => {}) {
  const state = ref<'idle' | 'connecting' | 'connected' | 'reconnecting' | 'disconnected'>('idle')
  const tracks = shallowRef<LiveTrack[]>([])
  const speaking = ref<string[]>([])
  const canPlayAudio = ref(true)
  const canPublish = ref<string[]>([])
  const mic = ref(false)
  const camera = ref(false)
  const screen = ref(false)
  const reason = ref<'replaced' | 'ended' | 'removed' | 'lost' | null>(null)
  let room: Room | null = null

  const isTeacher = (p: Participant) => p.identity.startsWith('t:')

  function collect() {
    if (!room) return
    const out: LiveTrack[] = []
    for (const p of [room.localParticipant, ...room.remoteParticipants.values()]) {
      for (const pub of p.trackPublications.values()) {
        if (!pub.track) continue
        out.push({
          key: `${p.identity}:${pub.trackSid}`, identity: p.identity, name: p.name || '', teacher: isTeacher(p),
          local: p === room.localParticipant, source: pub.source, track: pub.track, muted: pub.isMuted,
        })
      }
    }
    tracks.value = out
    const local = room.localParticipant
    mic.value = local.isMicrophoneEnabled
    camera.value = local.isCameraEnabled
    screen.value = local.isScreenShareEnabled
    canPublish.value = local.permissions?.canPublish ? (local.permissions.canPublishSources ?? []).map(s => SOURCE_NAMES[s] ?? '') : []
  }

  async function connect(url: string, token: string) {
    disconnect()
    reason.value = null
    state.value = 'connecting'
    room = new Room({
      adaptiveStream: true,
      dynacast: true,
      videoCaptureDefaults: { resolution: VideoPresets.h720.resolution },
      publishDefaults: { simulcast: true, videoSimulcastLayers: [VideoPresets.h180, VideoPresets.h360], screenShareSimulcastLayers: [] },
      audioCaptureDefaults: { echoCancellation: true, noiseSuppression: true, autoGainControl: true },
    })
    const r = room
    const update = () => r === room && collect()
    r.on(RoomEvent.TrackSubscribed, update).on(RoomEvent.TrackUnsubscribed, update).on(RoomEvent.TrackMuted, update).on(RoomEvent.TrackUnmuted, update)
      .on(RoomEvent.LocalTrackPublished, update).on(RoomEvent.LocalTrackUnpublished, update)
      .on(RoomEvent.TrackPublished, update).on(RoomEvent.TrackUnpublished, update)
      .on(RoomEvent.ParticipantConnected, update).on(RoomEvent.ParticipantDisconnected, update)
      .on(RoomEvent.ParticipantPermissionsChanged, update)
      .on(RoomEvent.ActiveSpeakersChanged, (ps: Participant[]) => (speaking.value = ps.map(p => p.identity)))
      .on(RoomEvent.AudioPlaybackStatusChanged, () => (canPlayAudio.value = r.canPlaybackAudio))
      .on(RoomEvent.DataReceived, (payload: Uint8Array, _p?: unknown, _k?: unknown, topic?: string) => {
        if (topic !== 'manhagy') return
        try {
          onSignal(String(JSON.parse(new TextDecoder().decode(payload)).type))
        }
        catch { /* not ours */ }
      })
      .on(RoomEvent.ConnectionStateChanged, (s: ConnectionState) => {
        state.value = s === ConnectionState.Connected ? 'connected' : s === ConnectionState.Reconnecting || s === ConnectionState.SignalReconnecting ? 'reconnecting' : s === ConnectionState.Connecting ? 'connecting' : 'disconnected'
      })
      .on(RoomEvent.Disconnected, (why?: DisconnectReason) => {
        reason.value = why === DisconnectReason.DUPLICATE_IDENTITY
          ? 'replaced'
          : why === DisconnectReason.ROOM_DELETED
            ? 'ended'
            : why === DisconnectReason.PARTICIPANT_REMOVED ? 'removed' : why === DisconnectReason.CLIENT_INITIATED ? null : 'lost'
        tracks.value = []
      })
    try {
      await r.connect(url, token)
      canPlayAudio.value = r.canPlaybackAudio
      collect()
    }
    catch (e) {
      state.value = 'disconnected'
      reason.value = 'lost'
      throw e
    }
  }

  async function toggle(kind: 'mic' | 'camera' | 'screen', on: boolean) {
    const p = room?.localParticipant
    if (!p) return
    if (kind === 'mic') await p.setMicrophoneEnabled(on)
    else if (kind === 'camera') await p.setCameraEnabled(on)
    else await p.setScreenShareEnabled(on, { audio: true, resolution: { width: 1920, height: 1080, frameRate: 15 }, contentHint: 'detail' })
    collect()
  }

  /** Browsers start a page's sound only after a tap. */
  async function startAudio() {
    await room?.startAudio()
    canPlayAudio.value = room?.canPlaybackAudio ?? true
  }

  function disconnect() {
    const r = room
    room = null
    r?.removeAllListeners()
    r?.disconnect()
    tracks.value = []
    mic.value = camera.value = screen.value = false
    state.value = 'idle'
  }

  onBeforeUnmount(disconnect)

  return { state, tracks, speaking, canPlayAudio, canPublish, mic, camera, screen, reason, connect, toggle, startAudio, disconnect }
}

/** LiveKit's numeric track sources in permissions → their names. */
const SOURCE_NAMES: Record<number, string> = { 1: 'camera', 2: 'microphone', 3: 'screen_share', 4: 'screen_share_audio' }
