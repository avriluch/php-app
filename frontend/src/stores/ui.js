import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useUIStore = defineStore('ui', () => {
  const sidebarOpen = ref(true)
  const toasts = ref([])
  let toastId = 0

  const addToast = (message, type = 'info', duration = 4000) => {
    const id = ++toastId
    toasts.value.push({ id, message, type })
    if (duration > 0) {
      setTimeout(() => removeToast(id), duration)
    }
    return id
  }

  const removeToast = (id) => {
    toasts.value = toasts.value.filter((t) => t.id !== id)
  }

  const toast = {
    success: (msg) => addToast(msg, 'success'),
    error: (msg) => addToast(msg, 'error'),
    info: (msg) => addToast(msg, 'info'),
    warning: (msg) => addToast(msg, 'warning'),
  }

  return { sidebarOpen, toasts, toast, removeToast }
})
