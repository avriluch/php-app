<script setup>
defineProps({
  variant: { type: String, default: 'primary' },
  size: { type: String, default: 'md' },
  disabled: Boolean,
  loading: Boolean,
  type: { type: String, default: 'button' },
  as: { type: String, default: 'button' },
})

const variants = {
  primary: 'bg-primary-600 text-white hover:bg-primary-700 focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2',
  secondary: 'bg-white text-primary-600 border border-primary-600 hover:bg-primary-50',
  accent: 'bg-accent-500 text-white hover:bg-accent-600 focus-visible:ring-2 focus-visible:ring-accent-500 focus-visible:ring-offset-2',
  ghost: 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900',
  danger: 'bg-red-600 text-white hover:bg-red-700 focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2',
  outline: 'bg-transparent text-neutral-700 border border-neutral-300 hover:bg-neutral-50',
}

const sizes = {
  xs: 'px-2.5 py-1 text-xs',
  sm: 'px-3 py-1.5 text-sm',
  md: 'px-4 py-2 text-sm',
  lg: 'px-5 py-2.5 text-base',
  xl: 'px-6 py-3 text-base',
}
</script>

<template>
  <component
    :is="as"
    :type="as === 'button' ? type : undefined"
    :disabled="disabled || loading"
    :class="[
      'inline-flex items-center justify-center gap-2 font-medium rounded-lg transition-colors duration-150 outline-none cursor-pointer select-none',
      'disabled:opacity-50 disabled:cursor-not-allowed',
      variants[variant],
      sizes[size],
    ]"
  >
    <span v-if="loading" class="inline-block w-4 h-4 border-2 border-current border-r-transparent rounded-full animate-spin" />
    <slot />
  </component>
</template>
