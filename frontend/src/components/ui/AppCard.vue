<script setup>
defineProps({
  padding: { type: String, default: 'md' },
  hover: Boolean,
  as: { type: String, default: 'div' },
})

const paddings = {
  none: '',
  sm: 'p-4',
  md: 'p-6',
  lg: 'p-8',
}
</script>

<template>
  <component
    :is="as"
    :class="[
      'bg-white rounded-xl border border-neutral-200',
      paddings[padding],
      hover && 'transition-shadow duration-200 hover:shadow-md cursor-pointer',
    ]"
    style="box-shadow: var(--shadow-card)"
  >
    <slot />
  </component>
</template>
