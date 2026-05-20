<script setup>
defineProps({
  variant: { type: String, default: 'default' },
  size: { type: String, default: 'sm' },
})

const variants = {
  default: 'bg-neutral-100 text-neutral-700',
  primary: 'bg-primary-100 text-primary-700',
  accent: 'bg-accent-100 text-accent-700',
  success: 'bg-emerald-100 text-emerald-700',
  warning: 'bg-amber-100 text-amber-700',
  danger: 'bg-red-100 text-red-700',
  info: 'bg-blue-100 text-blue-700',
  pending: 'bg-yellow-100 text-yellow-700',
  confirmed: 'bg-accent-100 text-accent-700',
  cancelled: 'bg-red-100 text-red-700',
  paid: 'bg-primary-100 text-primary-700',
}

const sizes = {
  xs: 'px-2 py-0.5 text-xs',
  sm: 'px-2.5 py-1 text-xs',
  md: 'px-3 py-1.5 text-sm',
}
</script>

<template>
  <span :class="['inline-flex items-center gap-1 font-medium rounded-full', variants[variant], sizes[size]]">
    <slot />
  </span>
</template>
