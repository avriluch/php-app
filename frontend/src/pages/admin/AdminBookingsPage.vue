<script setup>
import { ref, watch, onMounted } from 'vue'
import {
  Calendar, Clock, Video, MapPin, ChevronDown, CalendarRange, User as UserIcon, Briefcase,
} from '@lucide/vue'
import api from '@/services/api'
import AppCard from '@/components/ui/AppCard.vue'
import AppSpinner from '@/components/ui/AppSpinner.vue'
import AppBadge from '@/components/ui/AppBadge.vue'

const loading = ref(true)
const error = ref(null)
const bookings = ref([])
const meta = ref({ current_page: 1, last_page: 1, total: 0, per_page: 20 })

const page = ref(1)
const estadoFilter = ref('')
// 'mes' = mes actual (default) · '30dias' = últimos 30 días · 'todo' = sin filtro de fecha
const periodFilter = ref('mes')

const estadoConfig = {
  pendiente:   { label: 'Pendiente',   variant: 'warning' },
  confirmada:  { label: 'Confirmada',  variant: 'info' },
  pagada:      { label: 'Pagada',      variant: 'paid' },
  en_curso:    { label: 'En curso',    variant: 'primary' },
  finalizada:  { label: 'Finalizada',  variant: 'success' },
  cancelada:   { label: 'Cancelada',   variant: 'danger' },
  no_asistida: { label: 'No asistió',  variant: 'default' },
}

const periodLabel = {
  mes: 'Este mes',
  '30dias': 'Últimos 30 días',
  todo: 'Todas',
}

const modalidadLabel = (m) => ({ virtual: 'Virtual', presencial: 'Presencial', hibrida: 'Híbrida' }[m] ?? m)

const formatFecha = (iso) => {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('es-UY', {
    weekday: 'short', day: 'numeric', month: 'short', year: 'numeric',
  })
}
const formatHora = (iso) =>
  iso ? new Date(iso).toLocaleTimeString('es-UY', { hour: '2-digit', minute: '2-digit' }) : ''

const money = (n) =>
  '$ ' + new Intl.NumberFormat('es-UY', { maximumFractionDigits: 0 }).format(n ?? 0)

function dateRangeForPeriod(p) {
  const now = new Date()
  if (p === 'mes') {
    const from = new Date(now.getFullYear(), now.getMonth(), 1, 0, 0, 0, 0)
    const to = new Date(now.getFullYear(), now.getMonth() + 1, 0, 23, 59, 59, 999)
    return { from: from.toISOString(), to: to.toISOString() }
  }
  if (p === '30dias') {
    const from = new Date(now.getTime() - 30 * 24 * 60 * 60 * 1000)
    return { from: from.toISOString(), to: now.toISOString() }
  }
  return { from: undefined, to: undefined }
}

async function load() {
  loading.value = true
  error.value = null
  try {
    const range = dateRangeForPeriod(periodFilter.value)
    const { data } = await api.get('/bookings', {
      params: {
        estado: estadoFilter.value || undefined,
        from: range.from,
        to: range.to,
        page: page.value,
        per_page: 20,
        order: 'desc',
      },
    })
    bookings.value = data.data ?? []
    meta.value = data.meta ?? { current_page: 1, last_page: 1, total: 0, per_page: 20 }
  } catch (e) {
    error.value = e.response?.data?.message ?? 'No se pudieron cargar las reservas.'
    bookings.value = []
  } finally {
    loading.value = false
  }
}

watch(estadoFilter, () => {
  page.value = 1
  load()
})
watch(periodFilter, () => {
  page.value = 1
  load()
})
watch(page, load)

function goToPage(p) {
  if (p >= 1 && p <= meta.value.last_page) page.value = p
}

onMounted(load)
</script>

<template>
  <div>
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-neutral-900">Reservas</h1>
      <p class="text-neutral-500 mt-1 text-sm">
        Todas las reservas de la plataforma — {{ periodLabel[periodFilter] }}.
      </p>
    </div>

    <!-- Filtros -->
    <AppCard padding="sm" class="mb-4">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <p class="text-xs text-neutral-500">
          {{ meta.total }} reserva{{ meta.total === 1 ? '' : 's' }} en total
        </p>
        <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
          <div class="relative">
            <CalendarRange class="w-4 h-4 text-neutral-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
            <select
              v-model="periodFilter"
              class="appearance-none w-full sm:w-48 pl-9 pr-9 py-2 text-sm border border-neutral-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-primary-500 cursor-pointer"
            >
              <option value="mes">Este mes</option>
              <option value="30dias">Últimos 30 días</option>
              <option value="todo">Todas</option>
            </select>
            <ChevronDown class="w-4 h-4 text-neutral-400 absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none" />
          </div>

          <div class="relative">
            <select
              v-model="estadoFilter"
              class="appearance-none w-full sm:w-48 pl-3 pr-9 py-2 text-sm border border-neutral-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-primary-500 cursor-pointer"
            >
              <option value="">Todos los estados</option>
              <option value="pendiente">Pendiente</option>
              <option value="confirmada">Confirmada</option>
              <option value="pagada">Pagada</option>
              <option value="en_curso">En curso</option>
              <option value="finalizada">Finalizada</option>
              <option value="cancelada">Cancelada</option>
              <option value="no_asistida">No asistió</option>
            </select>
            <ChevronDown class="w-4 h-4 text-neutral-400 absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none" />
          </div>
        </div>
      </div>
    </AppCard>

    <!-- Listado -->
    <div v-if="loading" class="flex justify-center py-16">
      <AppSpinner size="lg" />
    </div>

    <p v-else-if="error" class="text-red-600 text-sm py-6 text-center">{{ error }}</p>

    <AppCard v-else-if="bookings.length === 0" class="text-center py-16">
      <Calendar class="w-12 h-12 text-neutral-300 mx-auto mb-3" />
      <p class="font-medium text-neutral-700">No hay reservas en este periodo</p>
      <p class="text-sm text-neutral-500 mt-1">Probá cambiando los filtros.</p>
    </AppCard>

    <div v-else class="space-y-3">
      <AppCard v-for="b in bookings" :key="b.id" padding="md">
        <div class="flex flex-col sm:flex-row sm:items-start gap-4">
          <!-- Fecha -->
          <div class="shrink-0 w-16 h-16 rounded-xl bg-primary-50 flex flex-col items-center justify-center text-primary-700">
            <span class="text-xl font-bold leading-none">
              {{ b.fecha_hora ? new Date(b.fecha_hora).getDate() : '—' }}
            </span>
            <span v-if="b.fecha_hora" class="text-xs font-medium uppercase">
              {{ new Date(b.fecha_hora).toLocaleDateString('es-UY', { month: 'short' }) }}
            </span>
          </div>

          <!-- Info principal -->
          <div class="flex-1 min-w-0">
            <div class="flex items-start justify-between gap-2 flex-wrap">
              <div class="min-w-0">
                <p class="font-semibold text-neutral-900 truncate">
                  {{ b.service?.nombre ?? 'Servicio' }}
                </p>
                <div class="mt-1 text-sm space-y-0.5">
                  <p class="text-neutral-600 flex items-center gap-1.5 min-w-0">
                    <UserIcon class="w-3.5 h-3.5 text-neutral-400 shrink-0" />
                    <span class="truncate">
                      {{ b.client?.nombre }} {{ b.client?.apellido }}
                      <span v-if="b.client?.email" class="text-neutral-400">· {{ b.client.email }}</span>
                    </span>
                  </p>
                  <p class="text-primary-600 flex items-center gap-1.5 min-w-0">
                    <Briefcase class="w-3.5 h-3.5 text-neutral-400 shrink-0" />
                    <span class="truncate">
                      {{ b.professional?.nombre }} {{ b.professional?.apellido }}
                      <span v-if="b.professional?.titulo" class="text-neutral-400">
                        · {{ b.professional.titulo }}
                      </span>
                    </span>
                  </p>
                </div>
              </div>
              <AppBadge :variant="estadoConfig[b.estado]?.variant ?? 'default'" size="sm">
                {{ estadoConfig[b.estado]?.label ?? b.estado }}
              </AppBadge>
            </div>

            <div class="flex flex-wrap gap-x-4 gap-y-1 mt-3 text-xs text-neutral-500">
              <span class="flex items-center gap-1">
                <Clock class="w-3.5 h-3.5" />
                {{ formatFecha(b.fecha_hora) }}<span v-if="b.fecha_hora">, {{ formatHora(b.fecha_hora) }}</span>
              </span>
              <span class="flex items-center gap-1">
                <component :is="b.modalidad === 'virtual' ? Video : MapPin" class="w-3.5 h-3.5" />
                {{ modalidadLabel(b.modalidad) }}
              </span>
              <span v-if="b.service?.duracion">{{ b.service.duracion }} min</span>
              <span v-if="b.payment">
                {{ money(b.payment.monto) }} · {{ b.payment.estado }}
              </span>
              <span v-else-if="b.package_purchase_id" class="text-neutral-400">
                Sesión de paquete
              </span>
            </div>

            <p
              v-if="b.estado === 'cancelada' && b.cancel_motivo"
              class="mt-2 text-xs text-red-600 italic truncate"
            >
              Motivo: {{ b.cancel_motivo }}
            </p>
          </div>
        </div>
      </AppCard>
    </div>

    <!-- Paginación -->
    <div
      v-if="!loading && !error && meta.last_page > 1"
      class="flex items-center justify-between mt-4 pt-4 border-t border-neutral-100"
    >
      <button
        class="text-sm px-3 py-1.5 rounded-lg border border-neutral-300 text-neutral-600 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-neutral-50 cursor-pointer"
        :disabled="meta.current_page <= 1"
        @click="goToPage(meta.current_page - 1)"
      >
        Anterior
      </button>
      <span class="text-xs text-neutral-500">
        Página {{ meta.current_page }} de {{ meta.last_page }}
      </span>
      <button
        class="text-sm px-3 py-1.5 rounded-lg border border-neutral-300 text-neutral-600 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-neutral-50 cursor-pointer"
        :disabled="meta.current_page >= meta.last_page"
        @click="goToPage(meta.current_page + 1)"
      >
        Siguiente
      </button>
    </div>
  </div>
</template>
