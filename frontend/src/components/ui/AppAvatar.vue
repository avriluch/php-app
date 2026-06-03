<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  src: String,
  name: { type: String, default: '' },
  size: { type: String, default: 'md' },
})

const sizes = {
  xs: 'w-6 h-6 text-xs',
  sm: 'w-8 h-8 text-sm',
  md: 'w-10 h-10 text-sm',
  lg: 'w-12 h-12 text-base',
  xl: 'w-16 h-16 text-lg',
  '2xl': 'w-20 h-20 text-xl',
}

const initials = (name) => {
  if (!name) return '?'
  return name
    .split(' ')
    .slice(0, 2)
    .map((w) => w[0]?.toUpperCase() ?? '')
    .join('')
}

const colors = [
  'bg-primary-200 text-primary-800',
  'bg-accent-200 text-accent-800',
  'bg-purple-200 text-purple-800',
  'bg-orange-200 text-orange-800',
  'bg-pink-200 text-pink-800',
]

const colorIndex = (name) => {
  if (!name) return 0
  return name.charCodeAt(0) % colors.length
}

const imgError = ref(false)
watch(() => props.src, () => { imgError.value = false })
</script>

<template>
  <span :class="['inline-flex items-center justify-center rounded-full font-semibold overflow-hidden shrink-0', sizes[size]]">
    <img
      v-if="src && !imgError"
      :src="src"
      :alt="name"
      class="w-full h-full object-cover"
      @error="imgError = true"
    />
    <span v-else :class="colors[colorIndex(name)]" class="w-full h-full flex items-center justify-center">
      {{ initials(name) }}
    </span>
  </span>
</template>
