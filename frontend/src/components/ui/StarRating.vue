<script setup>
import { ref } from 'vue'
import { Star } from '@lucide/vue'

const props = defineProps({
  modelValue: { type: Number, default: 0 },
  readonly: { type: Boolean, default: false },
  size: { type: String, default: 'md' },
})

const emit = defineEmits(['update:modelValue'])

const hovered = ref(0)

const sizes = { sm: 'w-4 h-4', md: 'w-6 h-6', lg: 'w-8 h-8' }

function select(n) {
  if (!props.readonly) emit('update:modelValue', n)
}
</script>

<template>
  <div class="flex items-center gap-0.5">
    <button
      v-for="n in 5"
      :key="n"
      type="button"
      :disabled="readonly"
      :class="['transition-transform focus:outline-none', !readonly ? 'hover:scale-110 cursor-pointer' : 'cursor-default']"
      @mouseenter="!readonly && (hovered = n)"
      @mouseleave="!readonly && (hovered = 0)"
      @click="select(n)"
    >
      <Star
        :class="[
          sizes[size],
          'transition-colors',
          n <= (hovered || modelValue) ? 'text-amber-400 fill-amber-400' : 'text-neutral-300 fill-neutral-100',
        ]"
      />
    </button>
  </div>
</template>
