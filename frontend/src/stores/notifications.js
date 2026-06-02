import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/services/api'
import echo from '@/services/echo'
import { useAuthStore } from '@/stores/auth'

/**
 * Centraliza la lista de notificaciones del usuario actual.
 *
 * - Carga inicial bajo demanda (init())
 * - Suscripción al canal privado del usuario para refrescar en vivo
 *   cuando llega el evento .nueva-reserva (profesional)
 * - Métodos para marcar leídas y refrescar manual
 *
 * El store es singleton por sesión; al hacer logout se llama destroy().
 */
export const useNotificationsStore = defineStore('notifications', () => {
  const items = ref([])
  const unreadCount = ref(0)
  const cargando = ref(false)
  const error = ref(null)

  let canalSuscrito = null
  let inicializado = false

  const ultimas = computed(() => items.value.slice(0, 5))

  async function cargar() {
    cargando.value = true
    error.value = null
    try {
      const { data } = await api.get('/notifications', { params: { per_page: 30 } })
      items.value = data.data ?? []
      unreadCount.value = data.meta?.unread_count ?? 0
    } catch (e) {
      error.value = e.response?.data?.message ?? 'No se pudieron cargar las notificaciones.'
    } finally {
      cargando.value = false
    }
  }

  async function marcarLeida(id) {
    const item = items.value.find((n) => n.id === id)
    if (!item || item.read_at) return
    try {
      const { data } = await api.patch(`/notifications/${id}/read`)
      item.read_at = data.read_at
      unreadCount.value = Math.max(0, unreadCount.value - 1)
    } catch (e) {
      error.value = e.response?.data?.message ?? 'No se pudo marcar la notificación.'
    }
  }

  async function marcarTodasLeidas() {
    if (!unreadCount.value) return
    try {
      await api.patch('/notifications/read-all')
      const ahora = new Date().toISOString()
      items.value = items.value.map((n) => (n.read_at ? n : { ...n, read_at: ahora }))
      unreadCount.value = 0
    } catch (e) {
      error.value = e.response?.data?.message ?? 'No se pudieron marcar las notificaciones.'
    }
  }

  function suscribir() {
    const auth = useAuthStore()
    if (!auth.user?.id || canalSuscrito) return

    // Si Reverb no está corriendo, la conexión falla en segundo plano y
    // pusher-js loguea solo. Igual envolvemos para que un error inesperado
    // no rompa la inicialización del store ni el render del bell.
    try {
      canalSuscrito = echo
        .private(`App.Models.User.${auth.user.id}`)
        .listen('.nueva-reserva', () => {
          // No tenemos el payload completo de la notificación creada por el job,
          // así que recargamos la lista para mantenerla sincronizada.
          cargar()
        })
    } catch (e) {
      console.warn('[notificaciones] No se pudo suscribir al canal WebSocket:', e?.message ?? e)
    }
  }

  function desuscribir() {
    const auth = useAuthStore()
    if (auth.user?.id && canalSuscrito) {
      try {
        echo.leave(`App.Models.User.${auth.user.id}`)
      } catch {
        // se ignora: si el canal ya cerró por otro motivo, no es problema
      }
      canalSuscrito = null
    }
  }

  async function init() {
    if (inicializado) return
    inicializado = true
    await cargar()
    suscribir()
  }

  function destroy() {
    desuscribir()
    items.value = []
    unreadCount.value = 0
    inicializado = false
  }

  return {
    items,
    ultimas,
    unreadCount,
    cargando,
    error,
    init,
    destroy,
    cargar,
    marcarLeida,
    marcarTodasLeidas,
  }
})
