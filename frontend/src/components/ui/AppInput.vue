<script setup>
defineProps({
  label: String,
  modelValue: { type: [String, Number], default: '' },
  type: { type: String, default: 'text' },
  placeholder: String,
  error: String,
  hint: String,
  disabled: Boolean,
  required: Boolean,
  id: String,
})

defineEmits(['update:modelValue'])
</script>

<template>
  <div class="flex flex-col gap-1">
    <label
      v-if="label"
      :for="id"
      class="text-sm font-medium text-neutral-700"
    >
      {{ label }}
      <span v-if="required" class="text-red-500 ml-0.5">*</span>
    </label>

    <input
      :id="id"
      :type="type"
      :value="modelValue"
      :placeholder="placeholder"
      :disabled="disabled"
      :required="required"
      :class="[
        'w-full px-3 py-2 text-sm rounded-lg border bg-white transition-colors duration-150 outline-none',
        'placeholder:text-neutral-400',
        'focus:ring-2 focus:ring-primary-500 focus:ring-offset-0 focus:border-primary-500',
        error
          ? 'border-red-400 focus:ring-red-400 focus:border-red-400'
          : 'border-neutral-300',
        disabled && 'bg-neutral-100 cursor-not-allowed opacity-60',
      ]"
      @input="$emit('update:modelValue', $event.target.value)"
    />

    <p v-if="error" class="text-xs text-red-600 flex items-center gap-1">
      {{ error }}
    </p>
    <p v-else-if="hint" class="text-xs text-neutral-500">{{ hint }}</p>
  </div>
</template>
