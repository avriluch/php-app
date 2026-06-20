<script setup>
import { ref, onMounted, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { Calendar, Clock, MapPin, Video, CheckCircle, XCircle, AlertCircle, Loader, Star } from '@lucide/vue'
import api from '@/services/api'
import AppCard from '@/components/ui/AppCard.vue'
import AppSpinner from '@/components/ui/AppSpinner.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import StarRating from '@/components/ui/StarRating.vue'
import AppCalendar from '@/components/ui/AppCalendar.vue'

const loading = ref(true)
const error = ref(null)
const bookings = ref([])

// id de la reserva con el form de reseña abierto
const reviewingId = ref(null)
const reviewForm = ref({ puntaje: 0, comentario: '' })
const reviewSubmitting = ref(false)
const reviewError = ref(null)
const cancellingId = ref(null)
const cancelConfirmationId = ref(null)
const cancelError = ref(null)

// Reschedule
const reschedulingId = ref(null)
const reschedulingBooking = ref(null)
const rescheduleForm = ref({ fecha: '' })
const slots = ref([])
const selectedSlot = ref(null)
const slotsLoading = ref(false)
const rescheduleLoading = ref(false)
const rescheduleError = ref(null)
const today = new Date().toISOString().split('T')[0]

const estadoConfig = {
  pendiente:   { label: 'Pendiente',   variant: 'default', icon: AlertCircle },
  confirmada:  { label: 'Confirmada',  variant: 'primary', icon: CheckCircle },
  pagada:      { label: 'Pagada',      variant: 'success', icon: CheckCircle },
  en_curso:    { label: 'En curso',    variant: 'primary', icon: Loader },
  finalizada:  { label: 'Finalizada',  variant: 'success', icon: CheckCircle },
  cancelada:   { label: 'Cancelada',   variant: 'danger',  icon: XCircle },
  no_asistida: { label: 'No asistida', variant: 'danger',  icon: XCircle },
}

const modalidadLabel = (m) => ({ virtual: 'Virtual', presencial: 'Presencial', hibrida: 'Híbrida' }[m] ?? m)

const formatFecha = (iso) => new Date(iso).toLocaleDateString('es-UY', {
  weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
})
const formatHora = (iso) => new Date(iso).toLocaleTimeString('es-UY', { hour: '2-digit', minute: '2-digit' })
const formatPrice = (n) => new Intl.NumberFormat('es-UY', { style: 'currency', currency: 'UYU' }).format(n)

const canCancelBooking = (booking) => {
  const isCancelableState = ['pendiente', 'confirmada', 'pagada'].includes(booking.estado)
  const isInFuture = new Date(booking.fecha_hora).getTime() > Date.now()
  return isCancelableState && isInFuture
}

const canRescheduleBooking = (booking) => {
  const isReschedulableState = ['pendiente', 'confirmada', 'pagada'].includes(booking.estado)
  const isInFuture = new Date(booking.fecha_hora).getTime() > Date.now()
  return isReschedulableState && isInFuture
}

function openReschedule(booking) {
  reschedulingId.value = booking.id
  reschedulingBooking.value = booking
  const fecha = new Date(booking.fecha_hora)
  rescheduleForm.value = { fecha: fecha.toISOString().split('T')[0] }
  selectedSlot.value = null
  slots.value = []
  rescheduleError.value = null
  loadRescheduleSlots(rescheduleForm.value.fecha)
}

function closeReschedule() {
  reschedulingId.value = null
  reschedulingBooking.value = null
  selectedSlot.value = null
  slots.value = []
  rescheduleError.value = null
}

async function loadRescheduleSlots(fecha) {
  if (!reschedulingBooking.value || !fecha) return
  slots.value = []
  selectedSlot.value = null
  slotsLoading.value = true

  try {
    const { data } = await api.get(`/professionals/${reschedulingBooking.value.professional.id}/availability`, {
      params: { service_id: reschedulingBooking.value.service.id, from: fecha, to: fecha },
    })
    const ahora = Date.now()
    slots.value = (data.slots ?? []).map((slot) => ({
      ...slot,
      available: slot.available && new Date(slot.start).getTime() > ahora,
    }))
  } catch {
    slots.value = []
  } finally {
    slotsLoading.value = false
  }
}

watch(() => rescheduleForm.value.fecha, loadRescheduleSlots)

async function confirmReschedule(booking) {
  if (!selectedSlot.value) {
    rescheduleError.value = 'Elegí un horario disponible.'
    return
  }

  const diffMs = new Date(selectedSlot.value.start).getTime() - Date.now()
  if (diffMs < 24 * 60 * 60 * 1000) {
    rescheduleError.value = 'La reserva debe reagendarse con al menos 24 horas de anticipación.'
    return
  }

  rescheduleLoading.value = true
  rescheduleError.value = null

  try {
    const { data } = await api.patch(`/bookings/${booking.id}/reschedule`, {
      fecha_hora: selectedSlot.value.start,
    })

    const idx = bookings.value.findIndex((b) => b.id === booking.id)
    if (idx !== -1) bookings.value[idx] = data
    closeReschedule()
  } catch (e) {
    rescheduleError.value = e.response?.data?.message ?? e.message ?? 'No se pudo reagendar la reserva.'
  } finally {
    rescheduleLoading.value = false
  }
}

onMounted(async () => {
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
})

function openReview(bookingId) {
  reviewingId.value = bookingId
  reviewForm.value = { puntaje: 0, comentario: '' }
  reviewError.value = null
}

function closeReview() {
  reviewingId.value = null
  reviewError.value = null
}

function openCancelConfirmation(booking) {
  cancelConfirmationId.value = booking.id
  cancelError.value = null
}

function closeCancelConfirmation() {
  cancelConfirmationId.value = null
  cancelError.value = null
}

async function confirmCancelBooking(booking) {
  cancellingId.value = booking.id
  cancelError.value = null

  try {
    const { data } = await api.patch(`/bookings/${booking.id}/cancel`)
    const idx = bookings.value.findIndex(b => b.id === booking.id)
    if (idx !== -1) bookings.value[idx] = data
    closeCancelConfirmation()
  } catch (e) {
    cancelError.value = e.response?.data?.message ?? 'No se pudo cancelar la reserva.'
  } finally {
    cancellingId.value = null
  }
}

async function submitReview(booking) {
  if (!reviewForm.value.puntaje) {
    reviewError.value = 'Seleccioná una puntuación.'
    return
  }
  reviewSubmitting.value = true
  reviewError.value = null
  try {
    const { data } = await api.post(`/bookings/${booking.id}/review`, reviewForm.value)
    const idx = bookings.value.findIndex(b => b.id === booking.id)
    if (idx !== -1) bookings.value[idx] = { ...bookings.value[idx], review: data }
    closeReview()
  } catch (e) {
    reviewError.value = e.response?.data?.message ?? 'No se pudo enviar la reseña.'
  } finally {
    reviewSubmitting.value = false
  }
}
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-neutral-900 mb-1">Mis reservas</h1>
        <p class="text-neutral-500 text-sm">Turnos que reservaste con profesionales.</p>
      </div>
      <AppButton variant="primary" size="sm" as="RouterLink" to="/professionals">
        + Nueva reserva
      </AppButton>
    </div>

    <div v-if="loading" class="flex justify-center py-16">
      <AppSpinner size="lg" />
    </div>

    <p v-else-if="error" class="text-red-600 text-sm">{{ error }}</p>

    <AppCard v-else-if="bookings.length === 0" class="text-center py-16">
      <Calendar class="w-12 h-12 text-neutral-300 mx-auto mb-3" />
      <p class="font-medium text-neutral-700 mb-1">No tenés reservas todavía</p>
      <p class="text-sm text-neutral-500 mb-6">Encontrá un profesional y reservá tu primer turno.</p>
      <AppButton variant="primary" as="RouterLink" to="/professionals">Buscar profesionales</AppButton>
    </AppCard>

    <div v-else class="space-y-3">
      <AppCard v-for="b in bookings" :key="b.id" padding="md">
        <div class="flex flex-col sm:flex-row sm:items-start gap-4">

          <!-- Fecha destacada -->
          <div class="shrink-0 w-16 h-16 rounded-xl bg-primary-50 flex flex-col items-center justify-center text-primary-700">
            <span class="text-xl font-bold leading-none">{{ new Date(b.fecha_hora).getDate() }}</span>
            <span class="text-xs font-medium uppercase">
              {{ new Date(b.fecha_hora).toLocaleDateString('es-UY', { month: 'short' }) }}
            </span>
          </div>

          <!-- Info principal -->
          <div class="flex-1 min-w-0">
            <div class="flex items-start justify-between gap-2 flex-wrap">
              <div>
                <p class="font-semibold text-neutral-900">{{ b.service?.nombre }}</p>
                <p class="text-sm text-primary-600">
                  {{ b.professional?.nombre }} {{ b.professional?.apellido }} · {{ b.professional?.titulo }}
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

            <div v-if="b.payment" class="mt-3 flex items-center justify-between">
              <span class="text-sm text-neutral-500">
                Pago:
                <span :class="{
                  'text-accent-600 font-medium': b.payment.estado === 'completado',
                  'text-red-600 font-medium': b.payment.estado === 'cancelado' || b.payment.estado === 'fallido',
                  'text-amber-600 font-medium': b.payment.estado === 'pendiente',
                  'text-neutral-500 font-medium': b.payment.estado === 'reembolsado'
                }">
                  {{ b.payment.estado }}
                </span>
                <span v-if="b.payment.metodo"> · {{ b.payment.metodo }}</span>
              </span>
              <span class="font-semibold text-neutral-900">{{ formatPrice(b.payment.monto) }}</span>
            </div>

            <!-- Reseña ya enviada -->
            <div v-if="b.review" class="mt-3 flex items-center gap-2 text-sm text-neutral-500">
              <StarRating :model-value="b.review.puntaje" readonly size="sm" />
              <span class="text-xs text-neutral-400">Tu reseña</span>
              <span v-if="b.review.comentario" class="italic text-neutral-500 truncate max-w-xs">
                "{{ b.review.comentario }}"
              </span>
            </div>

            <!-- Botones de acción -->
            <div class="mt-3 flex flex-wrap gap-2">
              <AppButton
                v-if="canCancelBooking(b)"
                variant="outline" size="sm"
                :class="'border-red-600 text-red-600 hover:bg-red-50'"
                :disabled="cancelConfirmationId === b.id"
                @click="openCancelConfirmation(b)"
              >
                Cancelar reserva
              </AppButton>

              <AppButton
                v-if="canRescheduleBooking(b)"
                variant="primary" size="sm"
                @click="openReschedule(b)"
              >
                Reagendar
              </AppButton>

              <AppButton
                v-if="b.payment?.estado === 'pendiente'"
                variant="primary" size="sm" as="RouterLink" :to="`/pay/${b.id}/${b.payment.id}`"
              >
                Completar pago
              </AppButton>

              <AppButton
                v-if="b.estado === 'en_curso' && (b.modalidad === 'virtual' || b.modalidad === 'hibrida')"
                variant="primary" size="sm" as="RouterLink" :to="`/call/${b.id}`"
              >
                <Video class="w-4 h-4 mr-1" /> Unirse a videollamada
              </AppButton>

              <AppButton
                v-if="b.estado === 'finalizada' && !b.review && reviewingId !== b.id"
                variant="outline" size="sm"
                @click="openReview(b.id)"
              >
                <Star class="w-4 h-4 mr-1" /> Calificar
              </AppButton>
            </div>

            <div v-if="cancelConfirmationId === b.id" class="mt-4">
              <AppCard padding="md" class="border-red-200 bg-red-50">
                <div class="flex flex-col gap-3">
                  <div>
                    <p class="text-sm text-neutral-600">Esta acción cancelará la reserva y enviará un mail de cancelación.</p>
                  </div>

                  <div class="flex flex-wrap gap-2">
                    <AppButton
                      variant="outline" size="sm"
                      :class="'border-red-600 text-red-600 hover:bg-red-50'"
                      :loading="cancellingId === b.id"
                      @click="confirmCancelBooking(b)"
                    >
                      {{ cancellingId === b.id ? 'Cancelando...' : 'Cancelar reserva' }}
                    </AppButton>
                    <AppButton variant="outline" size="sm" @click="closeCancelConfirmation">
                      Volver
                    </AppButton>
                  </div>
                  <p v-if="cancelError" class="text-sm text-red-600">{{ cancelError }}</p>
                </div>
              </AppCard>
            </div>

            <div v-if="reschedulingId === b.id" class="mt-4">
              <AppCard padding="md" class="border-primary-200 bg-primary-50">
                <div class="space-y-4">
                  <div>
                    <p class="font-semibold text-neutral-900">Reagendar reserva</p>
                    <p class="text-sm text-neutral-500">Elegí una nueva fecha y horario disponible.</p>
                  </div>

                  <div class="block">
                    <span class="text-sm font-medium text-neutral-700 block mb-2">Fecha</span>
                    <div class="max-w-sm bg-white rounded-xl p-4 border border-neutral-200 shadow-sm">
                      <AppCalendar
                        v-model="rescheduleForm.fecha"
                        :min="today"
                      />
                    </div>
                  </div>

                  <div v-if="slotsLoading" class="flex justify-center py-6">
                    <AppSpinner size="md" />
                  </div>

                  <div v-else-if="slots.length === 0 && rescheduleForm.fecha" class="text-sm text-neutral-500 text-center py-6">
                    No hay horarios disponibles para este día.
                  </div>

                  <div v-else-if="slots.length > 0" class="grid grid-cols-3 sm:grid-cols-4 gap-2">
                    <button
                      v-for="slot in slots"
                      :key="slot.start"
                      type="button"
                      :class="[
                        'py-2 px-3 rounded-lg text-sm font-medium border transition-colors',
                        !slot.available
                          ? 'bg-neutral-100 text-neutral-400 border-neutral-200 cursor-not-allowed'
                          : selectedSlot?.start === slot.start
                            ? 'bg-primary-600 text-white border-primary-600'
                            : 'bg-white text-neutral-700 border-neutral-300 hover:border-primary-400 hover:text-primary-600 cursor-pointer'
                      ]"
                      :disabled="!slot.available"
                      @click="selectedSlot = slot"
                    >
                      {{ formatHora(slot.start) }}
                    </button>
                  </div>

                  <p class="text-xs text-neutral-500">
                    La nueva fecha debe ser al menos 24 horas después de ahora.
                  </p>

                  <div class="flex flex-wrap gap-2">
                    <AppButton
                      variant="primary"
                      size="sm"
                      :loading="rescheduleLoading"
                      :disabled="!selectedSlot"
                      @click="confirmReschedule(b)"
                    >
                      Guardar cambios
                    </AppButton>
                    <AppButton variant="outline" size="sm" @click="closeReschedule">
                      Cancelar
                    </AppButton>
                  </div>

                  <p v-if="rescheduleError" class="text-sm text-red-600">{{ rescheduleError }}</p>
                </div>
              </AppCard>
            </div>

            <!-- Formulario de reseña inline -->
            <div
              v-if="reviewingId === b.id"
              class="mt-4 p-4 bg-neutral-50 rounded-xl border border-neutral-200"
            >
              <p class="text-sm font-semibold text-neutral-800 mb-3">
                ¿Cómo fue tu experiencia con {{ b.professional?.nombre }}?
              </p>

              <div class="mb-3">
                <StarRating v-model="reviewForm.puntaje" size="lg" />
                <p v-if="reviewForm.puntaje" class="text-xs text-neutral-500 mt-1">
                  {{ ['', 'Muy malo', 'Malo', 'Regular', 'Bueno', 'Excelente'][reviewForm.puntaje] }}
                </p>
              </div>

              <textarea
                v-model="reviewForm.comentario"
                placeholder="Contá tu experiencia (opcional)..."
                rows="3"
                class="w-full px-3 py-2 text-sm border border-neutral-300 rounded-lg resize-none focus:outline-none focus:ring-2 focus:ring-primary-500"
              />

              <p v-if="reviewError" class="text-red-600 text-xs mt-1">{{ reviewError }}</p>

              <div class="flex gap-2 mt-3">
                <AppButton
                  variant="primary" size="sm"
                  :disabled="reviewSubmitting"
                  @click="submitReview(b)"
                >
                  {{ reviewSubmitting ? 'Enviando...' : 'Enviar reseña' }}
                </AppButton>
                <AppButton variant="ghost" size="sm" @click="closeReview">
                  Cancelar
                </AppButton>
              </div>
            </div>

          </div>
        </div>
      </AppCard>
    </div>
  </div>
</template>