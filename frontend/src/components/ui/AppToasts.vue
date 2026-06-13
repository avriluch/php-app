<script setup>
import { CheckCircle2, AlertCircle, Info, AlertTriangle, X } from '@lucide/vue'
import { useUIStore } from '@/stores/ui'

const ui = useUIStore()

const config = {
  success: { icon: CheckCircle2, classes: 'bg-emerald-50 border-emerald-200 text-emerald-800' },
  error: { icon: AlertCircle, classes: 'bg-red-50 border-red-200 text-red-800' },
  warning: { icon: AlertTriangle, classes: 'bg-amber-50 border-amber-200 text-amber-800' },
  info: { icon: Info, classes: 'bg-blue-50 border-blue-200 text-blue-800' },
}
</script>

<template>
  <div class="fixed top-4 right-4 z-50 flex flex-col gap-2 w-80 max-w-[calc(100vw-2rem)] pointer-events-none">
    <TransitionGroup
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0 translate-x-4"
      enter-to-class="opacity-100 translate-x-0"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0 translate-x-4"
    >
      <div
        v-for="t in ui.toasts"
        :key="t.id"
        :class="[
          'pointer-events-auto flex items-start gap-2 px-4 py-3 rounded-xl border shadow-sm text-sm',
          (config[t.type] ?? config.info).classes,
        ]"
      >
        <component :is="(config[t.type] ?? config.info).icon" class="w-4 h-4 shrink-0 mt-0.5" />
        <span class="flex-1">{{ t.message }}</span>
        <button class="shrink-0 opacity-60 hover:opacity-100" @click="ui.removeToast(t.id)">
          <X class="w-4 h-4" />
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>
