import { defineStore, getActivePinia } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/services/api'
import { useNotificationsStore } from '@/stores/notifications'

/** Acepta usuario plano o envuelto en { data } (respuesta antigua de /auth/me). */
function normalizeUser(raw) {
  if (!raw) return null
  if (raw.data && typeof raw.data === 'object' && raw.nombre === undefined) {
    return raw.data
  }
  return raw
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref(normalizeUser(JSON.parse(localStorage.getItem('user') || 'null')))
  const token = ref(localStorage.getItem('token') || null)

  const isLoggedIn = computed(() => !!token.value)
  const role = computed(() => user.value?.role ?? null)
  const isClient = computed(() => role.value === 'client')
  const isProfessional = computed(() => role.value === 'professional')
  const isAdmin = computed(() => role.value === 'admin')
  const displayName = computed(() => {
    if (!user.value) return ''
    const n = [user.value.nombre, user.value.apellido].filter(Boolean).join(' ')
    return n || user.value.email || ''
  })

  const setToken = (tokenValue) => {
    token.value = tokenValue
    localStorage.setItem('token', tokenValue)
    api.defaults.headers.common['Authorization'] = `Bearer ${tokenValue}`
  }

  const setSession = (userData, tokenValue) => {
    user.value = normalizeUser(userData)
    setToken(tokenValue)
    localStorage.setItem('user', JSON.stringify(user.value))
  }

  const login = async (credentials) => {
    const { data } = await api.post('/auth/login', credentials)
    setSession(data.user, data.token)
    return data
  }

  const register = async (payload) => {
    const { data } = await api.post('/auth/register', payload)
    setSession(data.user, data.token)
    return data
  }

  const logout = () => {
    // Limpia el store de notificaciones y cierra el canal de Echo antes
    // de soltar el token, así la desuscripción aún tiene un user.id válido.
    if (getActivePinia()) {
      useNotificationsStore().destroy()
    }
    user.value = null
    token.value = null
    localStorage.removeItem('user')
    localStorage.removeItem('token')
    delete api.defaults.headers.common['Authorization']
  }

  const fetchMe = async () => {
    const { data } = await api.get('/auth/me')
    user.value = normalizeUser(data)
    localStorage.setItem('user', JSON.stringify(user.value))
    return user.value
  }

  // Restaurar header de axios al iniciar
  if (token.value) {
    api.defaults.headers.common['Authorization'] = `Bearer ${token.value}`
  }

  return {
    user,
    token,
    isLoggedIn,
    role,
    isClient,
    isProfessional,
    isAdmin,
    displayName,
    setToken,
    login,
    register,
    logout,
    fetchMe,
  }
})
