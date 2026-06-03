<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue'
import { MicOff } from '@lucide/vue'

const props = defineProps({
  track: Object,       // LiveKit VideoTrack | null
  name: { type: String, default: '' },
  muted: { type: Boolean, default: false },
  mirror: { type: Boolean, default: false },
})

const videoEl = ref(null)

function attachTrack(track) {
  if (track && videoEl.value) track.attach(videoEl.value)
}

function detachTrack(track) {
  if (track) track.detach()
}

onMounted(() => attachTrack(props.track))
onUnmounted(() => detachTrack(props.track))

watch(() => props.track, (next, prev) => {
  detachTrack(prev)
  attachTrack(next)
})
</script>

<template>
  <div class="relative bg-neutral-800 rounded-xl overflow-hidden flex items-center justify-center">
    <video
      v-show="track"
      ref="videoEl"
      autoplay
      playsinline
      :muted="muted"
      :class="['w-full h-full object-cover', mirror ? 'scale-x-[-1]' : '']"
    />
    <div v-if="!track" class="absolute inset-0 flex items-center justify-center">
      <div class="w-16 h-16 rounded-full bg-neutral-700 flex items-center justify-center text-white text-xl font-bold">
        {{ name?.[0]?.toUpperCase() ?? '?' }}
      </div>
    </div>
    <div class="absolute bottom-2 left-2 flex items-center gap-1">
      <span class="bg-black/50 text-white text-xs px-2 py-0.5 rounded-full">{{ name }}</span>
      <span v-if="muted" class="bg-black/50 text-red-400 p-0.5 rounded-full">
        <MicOff class="w-3 h-3" />
      </span>
    </div>
  </div>
</template>
