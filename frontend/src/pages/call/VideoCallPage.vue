<script setup>
import { ref, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Room, RoomEvent, Track, DisconnectReason } from 'livekit-client'
import { Mic, MicOff, Video, VideoOff, PhoneOff } from '@lucide/vue'
import api from '@/services/api'
import AppSpinner from '@/components/ui/AppSpinner.vue'
import VideoTile from '@/components/call/VideoTile.vue'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const bookingId = route.params.bookingId

const loading = ref(true)
const error = ref(null)
const enLlamada = ref(false)
/** Aviso cuando se entra sin cámara (PC de escritorio, etc.). */
const avisoSinCamara = ref(null)

const audioEnabled = ref(true)
const videoEnabled = ref(true)

const localVideoTrack = ref(null)
const localVideoEl = ref(null)

const remoteParticipants = ref([])
const audioEls = {}

let room = null
let salirVoluntario = false
/** Evita mostrar error genérico al cortar nosotros tras fallo de cámara/API. */
let limpiandoSesion = false

function mensajeDeError(e, fallback) {
  const data = e?.response?.data
  if (typeof data?.message === 'string' && data.message) return data.message
  if (typeof e?.message === 'string' && e.message && e.message !== 'Network Error') {
    return e.message
  }
  return fallback
}

function textoDesconexion(reason) {
  switch (reason) {
    case DisconnectReason.DUPLICATE_IDENTITY:
      return 'Ya hay otra pestaña o sesión conectada con tu usuario. Cerrá otras ventanas de la videollamada e intentá de nuevo.'
    case DisconnectReason.JOIN_FAILURE:
      return 'LiveKit rechazó la conexión. Verificá LIVEKIT_URL, LIVEKIT_API_KEY y LIVEKIT_API_SECRET en backend/.env (mismo proyecto en cloud.livekit.io).'
    case DisconnectReason.SIGNAL_CLOSE:
    case DisconnectReason.STATE_MISMATCH:
      return 'Se cortó la señal con LiveKit. Revisá tu red o que el proyecto LiveKit Cloud siga activo.'
    case DisconnectReason.MEDIA_FAILURE:
      return 'Falló el audio o video (cámara/micrófono). Probá otro navegador o permití permisos de dispositivo.'
    case DisconnectReason.CONNECTION_TIMEOUT:
      return 'Tiempo de espera agotado al conectar con LiveKit.'
    case DisconnectReason.CLIENT_INITIATED:
      return null
    default:
      return reason != null
        ? `LiveKit desconectó la sesión (código ${reason}). Revisá credenciales y permisos de cámara.`
        : 'Se perdió la conexión con LiveKit. Revisá credenciales LIVEKIT_* en backend/.env y permisos de cámara.'
  }
}

function getOrCreateRemote(participant) {
  let entry = remoteParticipants.value.find(p => p.identity === participant.identity)
  if (!entry) {
    const audioEl = document.createElement('audio')
    audioEl.autoplay = true
    document.body.appendChild(audioEl)
    audioEls[participant.identity] = audioEl
    entry = {
      identity: participant.identity,
      name: participant.name || participant.identity,
      videoTrack: null,
    }
    remoteParticipants.value = [...remoteParticipants.value, entry]
  }
  return entry
}

function removeRemote(identity) {
  audioEls[identity]?.remove()
  delete audioEls[identity]
  remoteParticipants.value = remoteParticipants.value.filter(p => p.identity !== identity)
}

async function attachLocalVideo() {
  await nextTick()
  const camPub = room?.localParticipant?.getTrackPublication(Track.Source.Camera)
  if (camPub?.track && localVideoEl.value) {
    localVideoTrack.value = camPub.track
    camPub.track.attach(localVideoEl.value)
  }
}

// Hay dos <video ref="localVideoEl"> en el template (el preview centrado mientras
// esperás, y el recuadro chico abajo a la derecha cuando ya hay otro participante).
// Solo uno existe en el DOM a la vez; al cambiar la cantidad de participantes, Vue
// monta el otro elemento y hay que volver a enganchar el track de la cámara, o el
// recuadro propio queda en negro / no aparece.
watch(() => remoteParticipants.value.length, async () => {
  if (enLlamada.value && videoEnabled.value) {
    await attachLocalVideo()
  }
})

function registrarEventosRoom(targetRoom) {
  targetRoom
    .on(RoomEvent.TrackSubscribed, (track, _pub, participant) => {
      const entry = getOrCreateRemote(participant)
      if (track.kind === Track.Kind.Video) {
        entry.videoTrack = track
        remoteParticipants.value = [...remoteParticipants.value]
      } else if (track.kind === Track.Kind.Audio) {
        track.attach(audioEls[participant.identity])
      }
    })
    .on(RoomEvent.TrackUnsubscribed, (track, _pub, participant) => {
      const entry = remoteParticipants.value.find(p => p.identity === participant.identity)
      if (entry && track.kind === Track.Kind.Video) {
        entry.videoTrack = null
        remoteParticipants.value = [...remoteParticipants.value]
      }
      track.detach()
    })
    .on(RoomEvent.ParticipantConnected, (participant) => {
      getOrCreateRemote(participant)
    })
    .on(RoomEvent.ParticipantDisconnected, (participant) => {
      removeRemote(participant.identity)
    })
    .on(RoomEvent.Disconnected, (reason) => {
      if (salirVoluntario || limpiandoSesion) return
      enLlamada.value = false
      loading.value = false
      if (error.value) return
      const msg = textoDesconexion(reason)
      if (msg) error.value = msg
    })
}

async function desconectarLimpio() {
  if (!room) return
  limpiandoSesion = true
  try {
    await room.disconnect()
  } catch {
    /* ignorar */
  } finally {
    limpiandoSesion = false
  }
}

function esErrorDispositivoNoEncontrado(e) {
  const nombre = e?.name ?? ''
  const msg = (e?.message ?? '').toLowerCase()
  return (
    nombre === 'NotFoundError'
    || nombre === 'DevicesNotFoundError'
    || msg.includes('not found')
    || msg.includes('requested device')
  )
}

async function activarDispositivos() {
  try {
    await room.localParticipant.setMicrophoneEnabled(true)
    await room.localParticipant.setCameraEnabled(true)
    await attachLocalVideo()
    return
  } catch (e) {
    if (!esErrorDispositivoNoEncontrado(e)) throw e
  }

  // Sin cámara: intentar solo micrófono
  try {
    videoEnabled.value = false
    await room.localParticipant.setCameraEnabled(false)
    await room.localParticipant.setMicrophoneEnabled(true)
    avisoSinCamara.value =
      'No se detectó cámara en este equipo. Entraste solo con micrófono. Para probar video, usá una laptop o celular.'
    return
  } catch {
    /* sin micrófono tampoco */
  }

  // Sin cámara ni micrófono: igual entrar para ver/escuchar al otro
  videoEnabled.value = false
  audioEnabled.value = false
  await room.localParticipant.setCameraEnabled(false)
  await room.localParticipant.setMicrophoneEnabled(false)
  avisoSinCamara.value =
    'Este equipo no tiene cámara ni micrófono. Podés permanecer en la sala y ver al otro cuando se conecte. Para una prueba completa, usá una laptop.'
}

onMounted(async () => {
  room = new Room()
  registrarEventosRoom(room)

  try {
    const { data } = await api.get(`/bookings/${bookingId}/livekit-token`)

    if (!data?.url || !data?.token) {
      error.value =
        'El servidor no devolvió credenciales de LiveKit. Reiniciá php artisan serve después de editar backend/.env.'
      return
    }

    try {
      await room.connect(data.url, data.token)
    } catch (e) {
      error.value = `No se pudo conectar a LiveKit: ${mensajeDeError(e, 'revisá LIVEKIT_URL en backend/.env')}`
      return
    }

    room.remoteParticipants.forEach((p) => {
      const entry = getOrCreateRemote(p)
      p.videoTrackPublications.forEach((pub) => {
        if (pub.track) entry.videoTrack = pub.track
      })
      p.audioTrackPublications.forEach((pub) => {
        if (pub.track) pub.track.attach(audioEls[p.identity])
      })
    })

    try {
      await activarDispositivos()
    } catch (e) {
      const nombre = e?.name ?? ''
      if (nombre === 'NotAllowedError' || nombre === 'PermissionDeniedError') {
        error.value =
          'El navegador bloqueó la cámara o el micrófono. Hacé clic en el candado de la barra de direcciones → Permitir cámara y micrófono → recargá.'
      } else {
        error.value = `No se pudo activar cámara/micrófono: ${mensajeDeError(e, 'dispositivo no disponible')}`
      }
      await desconectarLimpio()
      return
    }

    enLlamada.value = true
  } catch (e) {
    const status = e?.response?.status
    if (status === 422) {
      error.value =
        mensajeDeError(e, 'No se puede iniciar la videollamada.') +
        ' Asegurate de: (1) reserva en estado «en curso», (2) modalidad virtual o híbrida, (3) haber pulsado «Iniciar sesión» antes de «Unirse».'
    } else if (status === 403) {
      error.value = mensajeDeError(e, 'No tenés permiso para esta videollamada.')
    } else {
      error.value = mensajeDeError(e, 'No se pudo conectar a la videollamada.')
    }
    await desconectarLimpio()
  } finally {
    loading.value = false
  }
})

onUnmounted(() => {
  Object.values(audioEls).forEach(el => el.remove())
  salirVoluntario = true
  limpiandoSesion = true
  room?.disconnect().catch(() => {})
  room = null
})

const toggleAudio = async () => {
  if (!room?.localParticipant) return
  audioEnabled.value = !audioEnabled.value
  await room.localParticipant.setMicrophoneEnabled(audioEnabled.value)
}

const toggleVideo = async () => {
  if (!room?.localParticipant) return
  videoEnabled.value = !videoEnabled.value
  await room.localParticipant.setCameraEnabled(videoEnabled.value)
  if (videoEnabled.value) {
    await attachLocalVideo()
  } else {
    localVideoTrack.value = null
  }
}

const hangUp = async () => {
  salirVoluntario = true
  enLlamada.value = false
  await desconectarLimpio()
  router.back()
}
</script>

<template>
  <div class="h-screen bg-neutral-900 flex flex-col">

    <div v-if="loading" class="flex-1 flex flex-col items-center justify-center gap-3 text-white">
      <AppSpinner size="lg" class="text-primary-400" />
      <p class="text-neutral-400 text-sm">Conectando a la sesión...</p>
    </div>

    <div v-else-if="error" class="flex-1 flex flex-col items-center justify-center gap-4 text-white p-8 max-w-lg mx-auto">
      <p class="text-red-400 text-center text-sm leading-relaxed">{{ error }}</p>
      <button
        class="px-5 py-2 bg-primary-600 hover:bg-primary-700 rounded-lg text-sm font-medium transition-colors cursor-pointer"
        @click="router.back()"
      >
        Volver
      </button>
    </div>

    <template v-else-if="enLlamada">
      <div
        v-if="avisoSinCamara"
        class="shrink-0 px-4 py-2 bg-amber-500/20 border-b border-amber-500/40 text-amber-100 text-sm text-center"
      >
        {{ avisoSinCamara }}
      </div>

      <div class="flex-1 p-4 overflow-hidden relative">

        <div
          v-if="remoteParticipants.length === 0"
          class="h-full flex flex-col items-center justify-center gap-3 text-neutral-400"
        >
          <div class="w-20 h-20 rounded-full bg-neutral-800 flex items-center justify-center">
            <Video class="w-8 h-8" />
          </div>
          <p class="text-sm">Esperando al otro participante...</p>

          <div class="mt-4 w-64 h-40 rounded-xl overflow-hidden bg-neutral-800">
            <video
              ref="localVideoEl"
              autoplay
              playsinline
              muted
              class="w-full h-full object-cover scale-x-[-1]"
            />
          </div>
        </div>

        <div v-else class="h-full grid gap-3" :class="remoteParticipants.length === 1 ? 'grid-cols-1' : 'grid-cols-2'">
          <VideoTile
            v-for="p in remoteParticipants"
            :key="p.identity"
            :track="p.videoTrack"
            :name="p.name"
            class="h-full"
          />
        </div>

        <div
          v-if="remoteParticipants.length > 0"
          class="absolute bottom-24 right-4 w-36 h-24 sm:w-44 sm:h-28 rounded-xl overflow-hidden bg-neutral-800 border-2 border-neutral-700 shadow-xl"
        >
          <video
            ref="localVideoEl"
            autoplay
            playsinline
            muted
            class="w-full h-full object-cover scale-x-[-1]"
            :class="{ 'opacity-0': !videoEnabled }"
          />
          <div v-if="!videoEnabled" class="absolute inset-0 flex items-center justify-center">
            <div class="w-10 h-10 rounded-full bg-neutral-700 flex items-center justify-center text-white text-sm font-bold">
              {{ auth.displayName?.[0]?.toUpperCase() ?? 'Yo' }}
            </div>
          </div>
        </div>
      </div>

      <div class="h-20 bg-neutral-800 border-t border-neutral-700 flex items-center justify-center gap-4 px-6 shrink-0">
        <button
          :class="[
            'w-12 h-12 rounded-full flex items-center justify-center transition-colors cursor-pointer',
            audioEnabled ? 'bg-neutral-700 hover:bg-neutral-600 text-white' : 'bg-red-600 hover:bg-red-500 text-white',
          ]"
          :title="audioEnabled ? 'Silenciar' : 'Activar micrófono'"
          @click="toggleAudio"
        >
          <Mic v-if="audioEnabled" class="w-5 h-5" />
          <MicOff v-else class="w-5 h-5" />
        </button>

        <button
          :class="[
            'w-12 h-12 rounded-full flex items-center justify-center transition-colors cursor-pointer',
            videoEnabled ? 'bg-neutral-700 hover:bg-neutral-600 text-white' : 'bg-red-600 hover:bg-red-500 text-white',
          ]"
          :title="videoEnabled ? 'Apagar cámara' : 'Activar cámara'"
          @click="toggleVideo"
        >
          <Video v-if="videoEnabled" class="w-5 h-5" />
          <VideoOff v-else class="w-5 h-5" />
        </button>

        <button
          class="w-14 h-14 rounded-full bg-red-600 hover:bg-red-500 flex items-center justify-center text-white transition-colors cursor-pointer"
          title="Colgar"
          @click="hangUp"
        >
          <PhoneOff class="w-6 h-6" />
        </button>
      </div>
    </template>
  </div>
</template>
