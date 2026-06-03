<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { Calendar, Clock, Video, MapPin, CheckCircle, XCircle, AlertCircle, Loader, Play } from '@lucide/vue'
import api from '@/services/api'
import AppCard from '@/components/ui/AppCard.vue'
import AppSpinner from '@/components/ui/AppSpinner.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppBadge from '@/components/ui/AppBadge.vue'

const loading = ref(true)
const error = ref(null)
const bookings = ref([])
const updatingId = ref(null)

const estadoConfig = {
  pendiente:   { label: 'Pendiente',   variant: 'default',  icon: AlertCircle },
  confirmada:  { label: 'Confirmada',  variant: 'primary',  icon: CheckCircle },
  pagada:      { label: 'Pagada',      variant: 'success',  icon: CheckCircle },
  en_curso:    { label: 'En curso',    variant: 'primary',  icon: Loader },
  finalizada:  { label: 'Finalizada',  variant: 'success',  icon: CheckCircle },
  cancelada:   { label: 'Cancelada',   variant: 'danger',   icon: XCircle },
  no_asistida: { label: 'No asistida', variant: 'danger',   icon: XCircle },
}

const modalidadLabel = (m) => ({ virtual: 'Virtual', presencial: 'Presencial', hibrida: 'Híbrida' }[m] ?? m)

const formatFecha = (iso) => new Date(iso).toLocaleDateString('es-UY', {
  weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
})
const formatHora = (iso) => new Date(iso).toLocaleTimeString('es-UY', { hour: '2-digit', minute: '2-digit' })

async function load() {
  loading.value = true
  error.value = null
  try {
    const { data } = await api.get('/bookings')
    bookings.value = data.data ?? []
  } catch (e) {
    error.value = e.response?.data?.message ?? 'No se pudieron cargar las reservas.'
  } finally {
    loading.value = false
  }
}

async function changeStatus(bookingId, estado) {
  updatingId.value = bookingId
  try {
    const { data } = await api.patch(`/bookings/${bookingId}/status`, { estado })
    const idx = bookings.value.findIndex(b => b.id === bookingId)
    if (idx !== -1) bookings.value[idx] = data
  } catch (e) {
    alert(e.response?.data?.message ?? 'Error al actualizar el estado.')
  } finally {
    updatingId.value = null
  }
}

onMounted(load)
</script>

<template>
  <div>
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-neutral-900 mb-1">Mis reservas</h1>
      <p class="text-neutral-500 text-sm">Turnos de tus clientes.</p>
    </div>

    <div v-if="loading" class="flex justify-center py-16">
      <AppSpinner size="lg" />
    </div>

    <p v-else-if="error" class="text-red-600 text-sm">{{ error }}</p>

    <AppCard v-else-if="bookings.length === 0" class="text-center py-16">
      <Calendar class="w-12 h-12 text-neutral-300 mx-auto mb-3" />
      <p class="font-medium text-neutral-700">No tenés reservas todavía</p>
    </AppCard>

    <div v-else class="space-y-3">
      <AppCard v-for="b in bookings" :key="b.id" padding="md">
        <div class="flex flex-col sm:flex-row sm:items-start gap-4">

          <!-- Fecha -->
          <div class="shrink-0 w-16 h-16 rounded-xl bg-primary-50 flex flex-col items-center justify-center text-primary-700">
            <span class="text-xl font-bold leading-none">{{ new Date(b.fecha_hora).getDate() }}</span>
            <span class="text-xs font-medium uppercase">
              {{ new Date(b.fecha_hora).toLocaleDateString('es-UY', { month: 'short' }) }}
            </span>
          </div>

          <!-- Info -->
          <div class="flex-1 min-w-0">
            <div class="flex items-start justify-between gap-2 flex-wrap">
              <div>
                <p class="font-semibold text-neutral-900">{{ b.service?.nombre }}</p>
                <p class="text-sm text-primary-600">
                  {{ b.client?.nombre }} {{ b.client?.apellido }}
                  <span v-if="b.client?.email" class="text-neutral-400"> · {{ b.client.email }}</span>
                </p>
              </div>
              <AppBadge :variant="estadoConfig[b.estado]?.variant ?? 'default'" size="sm">
                {{ estadoConfig[b.estado]?.label ?? b.estado }}
              </AppBadge>
            </div>

            <div class="flex flex-wrap gap-4 mt-3 text-sm text-neutral-500">
              <span class="flex items-center gap-1">
                <Clock class="w-4 h-4" />
                {{ formatFecha(b.fecha_hora) }}, {{ formatHora(b.fecha_hora) }}
              </span>
              <span class="flex items-center gap-1">
                <component :is="b.modalidad === 'virtual' ? Video : MapPin" class="w-4 h-4" />
                {{ modalidadLabel(b.modalidad) }}
              </span>
              <span v-if="b.service?.duracion">{{ b.service.duracion }} min</span>
            </div>

            <!-- Acciones -->
            <div class="mt-3 flex flex-wrap gap-2">
              <!-- Iniciar sesión: solo si está pagada y es virtual/híbrida -->
              <AppButton
                v-if="b.estado === 'pagada'"
                variant="primary" size="sm"
                :disabled="updatingId === b.id"
                @click="changeStatus(b.id, 'en_curso')"
              >
                <Play class="w-4 h-4 mr-1" />
                {{ updatingId === b.id ? 'Iniciando...' : 'Iniciar sesión' }}
              </AppButton>

              <!-- Confirmar: si está pendiente -->
              <AppButton
                v-if="b.estado === 'pendiente'"
                variant="outline" size="sm"
                :disabled="updatingId === b.id"
                @click="changeStatus(b.id, 'confirmada')"
              >
                {{ updatingId === b.id ? 'Guardando...' : 'Confirmar' }}
              </AppButton>

              <!-- Unirse a videollamada -->
              <AppButton
                v-if="b.estado === 'en_curso' && (b.modalidad === 'virtual' || b.modalidad === 'hibrida')"
                variant="primary" size="sm" as="RouterLink" :to="`/call/${b.id}`"
              >
                <Video class="w-4 h-4 mr-1" /> Unirse a videollamada
              </AppButton>

              <!-- Finalizar -->
              <AppButton
                v-if="b.estado === 'en_curso'"
                variant="outline" size="sm"
                :disabled="updatingId === b.id"
                @click="changeStatus(b.id, 'finalizada')"
              >
                {{ updatingId === b.id ? 'Guardando...' : 'Finalizar sesión' }}
              </AppButton>
            </div>
          </div>

        </div>
      </AppCard>
    </div>
  </div>
</template>
