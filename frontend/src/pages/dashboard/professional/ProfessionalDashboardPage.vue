<script setup>
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { Calendar, BarChart2, Star, Clock, ChevronRight, Video, Play, AlertTriangle } from '@lucide/vue'
import api from '@/services/api'
import AppCard from '@/components/ui/AppCard.vue'
import AppSpinner from '@/components/ui/AppSpinner.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const statsLoading = ref(true)
const bookingsLoading = ref(true)
const updatingId = ref(null)

const stats = ref([
  { key: 'turnos_hoy',   label: 'Turnos hoy',        value: '—', icon: Calendar,  color: 'text-primary-600 bg-primary-50' },
  { key: 'reservas_mes', label: 'Reservas este mes',  value: '—', icon: BarChart2, color: 'text-accent-600 bg-accent-50' },
  { key: 'ingresos_mes', label: 'Ingresos del mes',   value: '—', icon: BarChart2, color: 'text-purple-600 bg-purple-50', currency: true },
  { key: 'calificacion', label: 'Calificación',       value: null, icon: Star,     color: 'text-amber-600 bg-amber-50', isRating: true },
])

const ratingTotal = ref(0)
const turnosHoy = ref([])
const porEstado = ref({})
const tieneAgenda = ref(true)
const serviciosActivos = ref(0)

const statusMeta = {
  pendiente: { label: 'Pendiente', variant: 'warning' },
  confirmada: { label: 'Confirmada', variant: 'info' },
  pagada: { label: 'Pagada', variant: 'paid' },
  en_curso: { label: 'En curso', variant: 'primary' },
  finalizada: { label: 'Finalizada', variant: 'success' },
  cancelada: { label: 'Cancelada', variant: 'danger' },
  no_asistida: { label: 'No asistió', variant: 'default' },
}

const bookingsByStatus = computed(() =>
  Object.entries(porEstado.value).map(([estado, total]) => ({
    estado,
    total,
    label: statusMeta[estado]?.label ?? estado,
    variant: statusMeta[estado]?.variant ?? 'default',
  })),
)

const formatPrice = (n) =>
  new Intl.NumberFormat('es-UY', { style: 'currency', currency: 'UYU', maximumFractionDigits: 0 }).format(n)

const formatHora = (iso) =>
  new Date(iso).toLocaleTimeString('es-UY', { hour: '2-digit', minute: '2-digit' })

const estadoConfig = {
  pendiente:  { label: 'Pendiente',  variant: 'default' },
  confirmada: { label: 'Confirmada', variant: 'primary' },
  pagada:     { label: 'Pagada',     variant: 'success' },
  en_curso:   { label: 'En curso',   variant: 'primary' },
  finalizada: { label: 'Finalizada', variant: 'success' },
}

async function changeStatus(booking, estado) {
  updatingId.value = booking.id
  try {
    await api.patch(`/bookings/${booking.id}/status`, { estado })
    booking.estado = estado
  } catch (e) {
    alert(e.response?.data?.message ?? 'Error al actualizar.')
  } finally {
    updatingId.value = null
  }
}

onMounted(async () => {
  const hoy = new Date()
  const desde = new Date(hoy.getFullYear(), hoy.getMonth(), hoy.getDate()).toISOString()
  const hasta = new Date(hoy.getFullYear(), hoy.getMonth(), hoy.getDate(), 23, 59, 59).toISOString()

  await Promise.all([
    api.get('/me/stats').then(({ data }) => {
      stats.value.forEach(s => {
        if (s.key in data) {
          s.value = s.currency
            ? formatPrice(data[s.key] ?? 0)
            : (data[s.key] ?? (s.isRating ? null : 0))
        }
      })
      ratingTotal.value = data.calificacion_total ?? 0
      porEstado.value = data.reservas_por_estado_mes ?? {}
    }).catch(() => {}).finally(() => { statsLoading.value = false }),

    api.get('/bookings', { params: { from: desde, to: hasta, per_page: 10 } })
      .then(({ data }) => {
        turnosHoy.value = (data.data ?? [])
          .filter(b => !['cancelada', 'no_asistida'].includes(b.estado))
          .sort((a, b) => new Date(a.fecha_hora) - new Date(b.fecha_hora))
      }).catch(() => {}).finally(() => { bookingsLoading.value = false }),

    api.get('/professional/agenda').then(({ data }) => {
      tieneAgenda.value = Boolean(data.agenda)
    }).catch(() => { tieneAgenda.value = false }),

    api.get('/professional/services').then(({ data }) => {
      serviciosActivos.value = (data.data ?? []).filter((s) => s.activo).length
    }).catch(() => {}),
  ])
})
</script>

<template>
  <div>
    <div class="mb-8">
      <h1 class="text-2xl font-bold text-neutral-900">Panel profesional</h1>
      <p class="text-neutral-500 mt-1">Hola {{ auth.displayName }}, aquí están tus métricas del día.</p>
    </div>

    <div
      v-if="!tieneAgenda"
      class="mb-6 px-4 py-3 rounded-lg bg-amber-50 border border-amber-200 text-sm text-amber-900 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3"
    >
      <div class="flex items-start gap-2">
        <AlertTriangle class="w-5 h-5 shrink-0 mt-0.5" />
        <p>
          <strong>Paso 1: configurá tu agenda.</strong>
          Antes de crear servicios, definí tus días y horarios de atención.
        </p>
      </div>
      <RouterLink
        to="/dashboard/professional/schedule"
        class="inline-flex items-center gap-1 font-medium underline shrink-0"
      >
        <Clock class="w-4 h-4" /> Configurar agenda
      </RouterLink>
    </div>

    <div
      v-else-if="tieneAgenda && serviciosActivos === 0"
      class="mb-6 px-4 py-3 rounded-lg bg-blue-50 border border-blue-200 text-sm text-blue-900 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3"
    >
      <p>
        <strong>Paso 2: publicá tu primer servicio.</strong>
        Ya tenés agenda; ahora creá al menos un servicio para que te reserven.
      </p>
      <RouterLink
        to="/dashboard/professional/services"
        class="inline-flex items-center gap-1 font-medium underline shrink-0"
      >
        Crear servicio
      </RouterLink>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
      <AppCard v-for="stat in stats" :key="stat.key" padding="sm">
        <div class="flex items-center gap-3">
          <div :class="['w-9 h-9 rounded-xl flex items-center justify-center shrink-0', stat.color]">
            <component :is="stat.icon" class="w-4 h-4" />
          </div>
          <div class="min-w-0">
            <div v-if="statsLoading" class="w-10 h-5 bg-neutral-200 rounded animate-pulse mb-1" />
            <template v-else-if="stat.isRating">
              <div class="flex items-center gap-1">
                <p class="text-xl font-bold text-neutral-900">
                  {{ stat.value !== null ? stat.value : '—' }}
                </p>
                <Star v-if="stat.value" class="w-4 h-4 text-amber-400 fill-amber-400" />
              </div>
              <p class="text-[10px] text-neutral-400">{{ ratingTotal }} reseña{{ ratingTotal !== 1 ? 's' : '' }}</p>
            </template>
            <p v-else class="text-xl font-bold text-neutral-900 truncate">{{ stat.value }}</p>
            <p class="text-xs text-neutral-500">{{ stat.label }}</p>
          </div>
        </div>
      </AppCard>
    </div>

    <!-- Agenda de hoy -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <AppCard>
        <div class="flex items-center justify-between mb-4">
          <h2 class="font-semibold text-neutral-900">Agenda de hoy</h2>
          <RouterLink
            to="/dashboard/professional/bookings"
            class="text-sm text-primary-600 hover:underline flex items-center gap-0.5 no-underline"
          >
            Ver todas <ChevronRight class="w-4 h-4" />
          </RouterLink>
        </div>

        <div v-if="bookingsLoading" class="flex justify-center py-8">
          <AppSpinner size="md" />
        </div>

        <div v-else-if="turnosHoy.length === 0" class="text-center py-8">
          <p class="text-sm text-neutral-500">No tenés turnos para hoy.</p>
        </div>

        <div v-else class="divide-y divide-neutral-100">
          <div
            v-for="b in turnosHoy"
            :key="b.id"
            class="py-3 first:pt-0 last:pb-0"
          >
            <div class="flex items-start justify-between gap-2">
              <div class="flex items-center gap-2 min-w-0">
                <Clock class="w-4 h-4 text-neutral-400 shrink-0" />
                <div class="min-w-0">
                  <p class="text-sm font-medium text-neutral-900 truncate">
                    {{ formatHora(b.fecha_hora) }} — {{ b.client?.nombre }} {{ b.client?.apellido }}
                  </p>
                  <p class="text-xs text-neutral-500 truncate">{{ b.service?.nombre }}</p>
                </div>
              </div>
              <AppBadge :variant="estadoConfig[b.estado]?.variant ?? 'default'" size="sm" class="shrink-0">
                {{ estadoConfig[b.estado]?.label ?? b.estado }}
              </AppBadge>
            </div>

            <!-- Acciones rápidas -->
            <div class="flex gap-2 mt-2 ml-6">
              <button
                v-if="b.estado === 'pagada'"
                class="text-xs px-2 py-1 bg-primary-600 hover:bg-primary-700 text-white rounded-md flex items-center gap-1 transition-colors cursor-pointer"
                :disabled="updatingId === b.id"
                @click="changeStatus(b, 'en_curso')"
              >
                <Play class="w-3 h-3" />
                {{ updatingId === b.id ? '...' : 'Iniciar' }}
              </button>
              <RouterLink
                v-if="b.estado === 'en_curso' && (b.modalidad === 'virtual' || b.modalidad === 'hibrida')"
                :to="`/call/${b.id}`"
                class="text-xs px-2 py-1 bg-primary-600 hover:bg-primary-700 text-white rounded-md flex items-center gap-1 no-underline transition-colors"
              >
                <Video class="w-3 h-3" /> Unirse
              </RouterLink>
              <button
                v-if="b.estado === 'en_curso'"
                class="text-xs px-2 py-1 border border-neutral-300 hover:bg-neutral-50 text-neutral-600 rounded-md transition-colors cursor-pointer"
                :disabled="updatingId === b.id"
                @click="changeStatus(b, 'finalizada')"
              >
                {{ updatingId === b.id ? '...' : 'Finalizar' }}
              </button>
            </div>
          </div>
        </div>
      </AppCard>

      <!-- Accesos rápidos -->
      <AppCard>
        <h2 class="font-semibold text-neutral-900 mb-4">Accesos rápidos</h2>
        <div class="space-y-2">
          <RouterLink
            v-for="link in [
              { to: '/dashboard/professional/bookings', label: 'Todas mis reservas', icon: Calendar },
              { to: '/dashboard/professional/schedule', label: 'Mi agenda y horarios', icon: Clock },
              { to: '/dashboard/professional/services', label: 'Mis servicios', icon: BarChart2 },
              { to: '/dashboard/professional/reviews', label: 'Reseñas recibidas', icon: Star },
            ]"
            :key="link.to"
            :to="link.to"
            class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-neutral-50 text-neutral-700 no-underline transition-colors"
          >
            <component :is="link.icon" class="w-4 h-4 text-neutral-400 shrink-0" />
            <span class="text-sm">{{ link.label }}</span>
            <ChevronRight class="w-4 h-4 text-neutral-300 ml-auto" />
          </RouterLink>
        </div>
      </AppCard>
    </div>

    <!-- Reservas del mes por estado -->
    <AppCard class="mt-6">
      <h2 class="font-semibold text-neutral-900 mb-4">Reservas del mes por estado</h2>
      <div v-if="statsLoading" class="flex justify-center py-6">
        <AppSpinner size="md" />
      </div>
      <p v-else-if="bookingsByStatus.length === 0" class="text-sm text-neutral-500 py-4 text-center">
        Todavía no hay reservas este mes.
      </p>
      <div v-else class="flex flex-wrap gap-2">
        <div
          v-for="s in bookingsByStatus"
          :key="s.estado"
          class="flex items-center gap-2 px-3 py-2 rounded-lg border border-neutral-100 bg-neutral-50/50"
        >
          <AppBadge :variant="s.variant" size="xs">{{ s.label }}</AppBadge>
          <span class="font-semibold text-neutral-900 text-sm">{{ s.total }}</span>
        </div>
      </div>
    </AppCard>
  </div>
</template>
