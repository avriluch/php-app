<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { Calendar, Clock, Star, CreditCard, MapPin, Video, ChevronRight } from '@lucide/vue'
import api from '@/services/api'
import AppCard from '@/components/ui/AppCard.vue'
import AppSpinner from '@/components/ui/AppSpinner.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const statsLoading = ref(true)
const bookingsLoading = ref(true)

const stats = ref([
  { key: 'proximas_reservas', label: 'Próximas reservas', value: '—', icon: Calendar, color: 'text-primary-600 bg-primary-50' },
  { key: 'sesiones_realizadas', label: 'Sesiones realizadas', value: '—', icon: Clock, color: 'text-accent-600 bg-accent-50' },
  { key: 'paquetes_activos', label: 'Paquetes activos', value: '—', icon: CreditCard, color: 'text-purple-600 bg-purple-50' },
  { key: 'resenas_escritas', label: 'Reseñas escritas', value: '—', icon: Star, color: 'text-amber-600 bg-amber-50' },
])

const proximasReservas = ref([])

const estadoConfig = {
  pendiente:  { label: 'Pendiente',  variant: 'default' },
  confirmada: { label: 'Confirmada', variant: 'primary' },
  pagada:     { label: 'Pagada',     variant: 'success' },
  en_curso:   { label: 'En curso',   variant: 'primary' },
}

const formatFecha = (iso) => new Date(iso).toLocaleDateString('es-UY', {
  weekday: 'short', day: 'numeric', month: 'short',
})
const formatHora = (iso) => new Date(iso).toLocaleTimeString('es-UY', { hour: '2-digit', minute: '2-digit' })

onMounted(async () => {
  // Stats y próximas reservas en paralelo
  await Promise.all([
    api.get('/me/stats').then(({ data }) => {
      stats.value.forEach(s => {
        if (s.key in data) s.value = data[s.key] ?? 0
      })
    }).catch(() => {}).finally(() => { statsLoading.value = false }),

    api.get('/bookings', {
      params: {
        from: new Date().toISOString(),
        order: 'asc',
        per_page: 3,
      },
    }).then(({ data }) => {
      proximasReservas.value = (data.data ?? [])
        .filter(b => ['pendiente', 'confirmada', 'pagada', 'en_curso'].includes(b.estado))
    }).catch(() => {}).finally(() => { bookingsLoading.value = false }),
  ])
})
</script>

<template>
  <div>
    <div class="mb-8">
      <h1 class="text-2xl font-bold text-neutral-900">Bienvenido, {{ auth.displayName }} 👋</h1>
      <p class="text-neutral-500 mt-1">Aquí podés gestionar tus reservas y sesiones.</p>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
      <AppCard v-for="stat in stats" :key="stat.key" padding="sm">
        <div class="flex items-center gap-3">
          <div :class="['w-9 h-9 rounded-xl flex items-center justify-center shrink-0', stat.color]">
            <component :is="stat.icon" class="w-4 h-4" />
          </div>
          <div>
            <p class="text-xl font-bold text-neutral-900">
              <span v-if="statsLoading" class="inline-block w-6 h-5 bg-neutral-200 rounded animate-pulse" />
              <span v-else>{{ stat.value }}</span>
            </p>
            <p class="text-xs text-neutral-500">{{ stat.label }}</p>
          </div>
        </div>
      </AppCard>
    </div>

    <!-- Próximas reservas -->
    <AppCard>
      <div class="flex items-center justify-between mb-4">
        <h2 class="font-semibold text-neutral-900">Próximas reservas</h2>
        <RouterLink
          to="/dashboard/client/bookings"
          class="text-sm text-primary-600 hover:underline flex items-center gap-0.5 no-underline"
        >
          Ver todas <ChevronRight class="w-4 h-4" />
        </RouterLink>
      </div>

      <div v-if="bookingsLoading" class="flex justify-center py-8">
        <AppSpinner size="md" />
      </div>

      <div v-else-if="proximasReservas.length === 0" class="text-center py-8">
        <p class="text-sm text-neutral-500 mb-3">No tenés reservas próximas.</p>
        <RouterLink
          to="/professionals"
          class="text-sm text-primary-600 hover:underline no-underline"
        >
          Buscar profesionales →
        </RouterLink>
      </div>

      <div v-else class="divide-y divide-neutral-100">
        <div
          v-for="b in proximasReservas"
          :key="b.id"
          class="flex items-center gap-3 py-3 first:pt-0 last:pb-0"
        >
          <div class="w-10 h-10 rounded-lg bg-primary-50 flex flex-col items-center justify-center text-primary-700 shrink-0">
            <span class="text-sm font-bold leading-none">{{ new Date(b.fecha_hora).getDate() }}</span>
            <span class="text-[10px] uppercase">{{ new Date(b.fecha_hora).toLocaleDateString('es-UY', { month: 'short' }) }}</span>
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-neutral-900 truncate">{{ b.service?.nombre }}</p>
            <p class="text-xs text-neutral-500 truncate">
              {{ b.professional?.nombre }} {{ b.professional?.apellido }} · {{ formatHora(b.fecha_hora) }}
            </p>
          </div>
          <div class="flex items-center gap-2 shrink-0">
            <component
              :is="b.modalidad === 'virtual' ? Video : MapPin"
              class="w-4 h-4 text-neutral-400"
            />
            <AppBadge :variant="estadoConfig[b.estado]?.variant ?? 'default'" size="sm">
              {{ estadoConfig[b.estado]?.label ?? b.estado }}
            </AppBadge>
          </div>
        </div>
      </div>
    </AppCard>
  </div>
</template>
