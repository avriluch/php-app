<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Room, RoomEvent, Track } from 'livekit-client'
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

const audioEnabled = ref(true)
const videoEnabled = ref(true)

const localVideoTrack = ref(null)
const localVideoEl = ref(null)

// [{ identity, name, videoTrack }]
const remoteParticipants = ref([])
// hidden <audio> elements keyed by identity
const audioEls = {}

const room = new Room()

function getOrCreateRemote(participant) {
  let entry = remoteParticipants.value.find(p => p.identity === participant.identity)
  if (!entry) {
    const audioEl = document.createElement('audio')
    audioEl.autoplay = true
    document.body.appendChild(audioEl)
    audioEls[participant.identity] = audioEl
    entry = { identity: participant.identity, name: participant.name || participant.identity, videoTrack: null }
    remoteParticipants.value = [...remoteParticipants.value, entry]
  }
  return entry
}

function removeRemote(identity) {
  audioEls[identity]?.remove()
  delete audioEls[identity]
  remoteParticipants.value = remoteParticipants.value.filter(p => p.identity !== identity)
}

onMounted(async () => {
  try {
    const { data } = await api.get(`/bookings/${bookingId}/livekit-token`)

    room
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
      .on(RoomEvent.Disconnected, () => {
        router.back()
      })

    await room.connect(data.url, data.token)

    // Participants already in the room
    room.remoteParticipants.forEach((p) => {
      const entry = getOrCreateRemote(p)
      p.videoTrackPublications.forEach((pub) => {
        if (pub.track) { entry.videoTrack = pub.track }
      })
      p.audioTrackPublications.forEach((pub) => {
        if (pub.track) pub.track.attach(audioEls[p.identity])
      })
    })

    await room.localParticipant.enableCameraAndMicrophone()
    await nextTick()

    const camPub = room.localParticipant.getTrackPublication(Track.Source.Camera)
    if (camPub?.track) {
      localVideoTrack.value = camPub.track
      if (localVideoEl.value) camPub.track.attach(localVideoEl.value)
    }

  } catch (e) {
    error.value = e.response?.data?.message || 'No se pudo conectar a la videollamada.'
  } finally {
    loading.value = false
  }
})

onUnmounted(() => {
  Object.values(audioEls).forEach(el => el.remove())
  room.disconnect()
})

const toggleAudio = async () => {
  audioEnabled.value = !audioEnabled.value
  await room.localParticipant.setMicrophoneEnabled(audioEnabled.value)
}

const toggleVideo = async () => {
  videoEnabled.value = !videoEnabled.value
  await room.localParticipant.setCameraEnabled(videoEnabled.value)
  if (videoEnabled.value) {
    await nextTick()
    const camPub = room.localParticipant.getTrackPublication(Track.Source.Camera)
    if (camPub?.track && localVideoEl.value) {
      localVideoTrack.value = camPub.track
      camPub.track.attach(localVideoEl.value)
    }
  } else {
    localVideoTrack.value = null
  }
}

const hangUp = async () => {
  await room.disconnect()
  router.back()
}
</script>

<template>
  <div class="h-screen bg-neutral-900 flex flex-col">

    <!-- Loading -->
    <div v-if="loading" class="flex-1 flex flex-col items-center justify-center gap-3 text-white">
      <AppSpinner size="lg" class="text-primary-400" />
      <p class="text-neutral-400 text-sm">Conectando a la sesión...</p>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="flex-1 flex flex-col items-center justify-center gap-4 text-white p-8">
      <p class="text-red-400 text-center">{{ error }}</p>
      <button
        class="px-5 py-2 bg-primary-600 hover:bg-primary-700 rounded-lg text-sm font-medium transition-colors cursor-pointer"
        @click="router.back()"
      >
        Volver
      </button>
    </div>

    <!-- Call UI -->
    <template v-else>
      <!-- Tiles area -->
      <div class="flex-1 p-4 overflow-hidden">

        <!-- Waiting for other participant -->
        <div
          v-if="remoteParticipants.length === 0"
          class="h-full flex flex-col items-center justify-center gap-3 text-neutral-400"
        >
          <div class="w-20 h-20 rounded-full bg-neutral-800 flex items-center justify-center">
            <Video class="w-8 h-8" />
          </div>
          <p class="text-sm">Esperando al otro participante...</p>

          <!-- Self preview centered while alone -->
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

        <!-- Grid cuando hay participantes -->
        <div v-else class="h-full grid gap-3" :class="remoteParticipants.length === 1 ? 'grid-cols-1' : 'grid-cols-2'">
          <VideoTile
            v-for="p in remoteParticipants"
            :key="p.identity"
            :track="p.videoTrack"
            :name="p.name"
            class="h-full"
          />
        </div>

        <!-- Local pip (esquina) cuando hay remotos -->
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

      <!-- Control bar -->
      <div class="h-20 bg-neutral-800 border-t border-neutral-700 flex items-center justify-center gap-4 px-6 shrink-0">
        <button
          :class="[
            'w-12 h-12 rounded-full flex items-center justify-center transition-colors cursor-pointer',
            audioEnabled ? 'bg-neutral-700 hover:bg-neutral-600 text-white' : 'bg-red-600 hover:bg-red-500 text-white'
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
            videoEnabled ? 'bg-neutral-700 hover:bg-neutral-600 text-white' : 'bg-red-600 hover:bg-red-500 text-white'
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
