import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/services/api'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(JSON.parse(localStorage.getItem('user') || 'null'))
  const token = ref(localStorage.getItem('token') || null)

  const isLoggedIn = computed(() => !!token.value)
  const role = computed(() => user.value?.role ?? null)
  const isClient = computed(() => role.value === 'client')
  const isProfessional = computed(() => role.value === 'professional')
  const isAdmin = computed(() => role.value === 'admin')

  const setSession = (userData, tokenValue) => {
    user.value = userData
    token.value = tokenValue
    localStorage.setItem('user', JSON.stringify(userData))
    localStorage.setItem('token', tokenValue)
    api.defaults.headers.common['Authorization'] = `Bearer ${tokenValue}`
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
    user.value = null
    token.value = null
    localStorage.removeItem('user')
    localStorage.removeItem('token')
    delete api.defaults.headers.common['Authorization']
  }

  const fetchMe = async () => {
    const { data } = await api.get('/auth/me')
    user.value = data
    localStorage.setItem('user', JSON.stringify(data))
    return data
  }

  // Restaurar header de axios al iniciar
  if (token.value) {
    api.defaults.headers.common['Authorization'] = `Bearer ${token.value}`
  }

  return { user, token, isLoggedIn, role, isClient, isProfessional, isAdmin, login, register, logout, fetchMe }
})
