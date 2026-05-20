<script setup>
defineProps({
  size: { type: String, default: 'md' },
  color: { type: String, default: 'primary' },
})

const sizes = {
  sm: 'w-4 h-4 border-2',
  md: 'w-6 h-6 border-2',
  lg: 'w-8 h-8 border-[3px]',
  xl: 'w-12 h-12 border-4',
}

const colors = {
  primary: 'border-primary-600 border-r-transparent',
  accent: 'border-accent-500 border-r-transparent',
  white: 'border-white border-r-transparent',
  neutral: 'border-neutral-400 border-r-transparent',
}
</script>

<template>
  <span
    role="status"
    aria-label="Cargando"
    :class="['inline-block rounded-full animate-spin', sizes[size], colors[color]]"
  />
</template>
