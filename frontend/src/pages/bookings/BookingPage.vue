<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Calendar, Clock, Video, MapPin, CheckCircle } from '@lucide/vue'
import api from '@/services/api'
import AppButton from '@/components/ui/AppButton.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppSpinner from '@/components/ui/AppSpinner.vue'
import AppBadge from '@/components/ui/AppBadge.vue'

const route = useRoute()
const router = useRouter()

const professionalId = Number(route.params.professionalId)
const packagePurchaseId = computed(() => {
  const q = route.query.package_purchase_id
  return q ? Number(q) : null
})

const step = ref(1)
const packagePurchase = ref(null)
const usingPackage = computed(() => Boolean(packagePurchase.value))
const loading = ref(true)
const submitting = ref(false)
const error = ref(null)

const professional = ref(null)
const services = ref([])
const selectedService = ref(null)
const selectedDate = ref('')
const slots = ref([])
const slotsLoading = ref(false)
const selectedSlot = ref(null)
const selectedModalidad = ref('')
const sinAgenda = ref(false)
const availabilityMessage = ref('')

const today = new Date().toISOString().split('T')[0]

const modalidadOptions = computed(() => {
  if (!selectedService.value) return []
  const m = selectedService.value.modalidad
  if (m === 'hibrida') return ['virtual', 'presencial']
  return [m]
})

const modalidadLabel = (m) => ({ virtual: 'Virtual', presencial: 'Presencial', hibrida: 'Híbrida' }[m] ?? m)
const formatPrice = (p) => new Intl.NumberFormat('es-UY', { style: 'currency', currency: 'UYU' }).format(p)
const formatSlotTime = (iso) => new Date(iso).toLocaleTimeString('es-UY', { hour: '2-digit', minute: '2-digit' })
const formatDateDisplay = (dateStr) => {
  if (!dateStr) return ''
  return new Date(dateStr + 'T12:00:00').toLocaleDateString('es-UY', { weekday: 'long', day: 'numeric', month: 'long' })
}

const bookableServices = computed(() => {
  const list = services.value ?? []
  if (usingPackage.value && packagePurchase.value?.service?.id) {
    return list.filter((s) => s.id === packagePurchase.value.service.id)
  }
  return list.filter((s) => s.type !== 'package')
})

async function loadData() {
  loading.value = true
  error.value = null
  try {
    const requests = [
      api.get(`/professionals/${professionalId}`),
      api.get(`/professionals/${professionalId}/services`),
    ]
    if (packagePurchaseId.value) {
      requests.push(api.get(`/package-purchases/${packagePurchaseId.value}`))
    }
    const results = await Promise.all(requests)
    professional.value = results[0].data
    services.value = results[1].data.data ?? []

    if (packagePurchaseId.value) {
      packagePurchase.value = results[2].data
      const profId = packagePurchase.value?.profesional?.id
      if (profId && profId !== professionalId) {
        error.value = 'Este paquete no corresponde a este profesional.'
        return
      }
      if (packagePurchase.value?.payment?.estado !== 'completado') {
        error.value = 'Completá el pago del paquete antes de reservar sesiones.'
        return
      }
      if ((packagePurchase.value?.sesiones_restantes ?? 0) <= 0) {
        error.value = 'Este paquete no tiene sesiones restantes.'
        return
      }
      const svc = packagePurchase.value?.service
      if (svc) {
        const match = services.value.find((s) => s.id === svc.id)
        if (match) {
          selectedService.value = match
          selectedModalidad.value = match.modalidad !== 'hibrida' ? match.modalidad : ''
          step.value = 2
        }
      }
    }
  } catch (e) {
    error.value = e.response?.data?.message ?? 'No se pudo cargar el profesional.'
  } finally {
    loading.value = false
  }
}

watch(selectedDate, async (date) => {
  if (!date || !selectedService.value) return
  slots.value = []
  selectedSlot.value = null
  sinAgenda.value = false
  availabilityMessage.value = ''
  slotsLoading.value = true
  try {
    const { data } = await api.get(`/professionals/${professionalId}/availability`, {
      params: { service_id: selectedService.value.id, from: date, to: date },
    })
    sinAgenda.value = Boolean(data.sin_agenda)
    availabilityMessage.value = data.mensaje ?? ''
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
})

function selectService(svc) {
  selectedService.value = svc
  selectedDate.value = ''
  selectedSlot.value = null
  selectedModalidad.value = svc.modalidad !== 'hibrida' ? svc.modalidad : ''
  step.value = 2
}

function isSlotInFuture(slot) {
  return slot?.start && new Date(slot.start).getTime() > Date.now()
}

function selectSlot(slot) {
  if (!slot.available || !isSlotInFuture(slot)) return
  selectedSlot.value = slot
  step.value = 3
}

async function confirmBooking() {
  if (!selectedSlot.value || !selectedModalidad.value) return
  if (!isSlotInFuture(selectedSlot.value)) {
    error.value = 'Ese horario ya pasó. Elegí otro turno.'
    step.value = 2
    return
  }
  submitting.value = true
  error.value = null
  try {
    const payload = {
      service_id: selectedService.value.id,
      professional_id: professionalId,
      fecha_hora: selectedSlot.value.start,
      modalidad: selectedModalidad.value,
    }
    if (usingPackage.value) {
      payload.package_purchase_id = packagePurchase.value.id
    }
    const { data } = await api.post('/bookings', payload)
    if (data.payment?.id) {
      router.push(`/pay/${data.id}/${data.payment.id}`)
    } else {
      router.push('/dashboard/client/bookings')
    }
  } catch (e) {
    error.value = e.response?.data?.message
      ?? e.response?.data?.errors?.fecha_hora?.[0]
      ?? 'Error al crear la reserva.'
    step.value = 2
  } finally {
    submitting.value = false
  }
}

onMounted(loadData)
</script>

<template>
  <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10">

    <div v-if="loading" class="flex justify-center py-20">
      <AppSpinner size="lg" />
    </div>

    <template v-else-if="professional">
      <div class="mb-8">
        <h1 class="text-2xl font-bold text-neutral-900 mb-1">
          {{ usingPackage ? 'Reservar sesión del paquete' : 'Reservar turno' }}
        </h1>
        <p class="text-neutral-500">con {{ professional.nombre }} {{ professional.apellido }} · {{ professional.titulo }}</p>
        <p v-if="usingPackage" class="text-sm text-primary-600 mt-1">
          {{ packagePurchase.sesiones_restantes }} sesión(es) restante(s) · {{ packagePurchase.service?.nombre }}
        </p>
      </div>

      <!-- Indicador de pasos -->
      <div class="flex items-center gap-4 mb-8">
        <div
          v-for="(label, i) in ['Servicio', 'Fecha y hora', 'Confirmar']"
          :key="i"
          :class="['flex items-center gap-2 text-sm font-medium',
            step > i + 1 ? 'text-accent-600' : step === i + 1 ? 'text-primary-600' : 'text-neutral-400']"
        >
          <span :class="['w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold border-2',
            step > i + 1 ? 'bg-accent-500 border-accent-500 text-white' :
            step === i + 1 ? 'border-primary-600 text-primary-600' :
            'border-neutral-300 text-neutral-400']">
            <CheckCircle v-if="step > i + 1" class="w-4 h-4" />
            <span v-else>{{ i + 1 }}</span>
          </span>
          <span class="hidden sm:block">{{ label }}</span>
        </div>
      </div>

      <!-- PASO 1: Servicio -->
      <div v-if="step === 1">
        <h2 class="text-lg font-semibold text-neutral-900 mb-4">Elegí un servicio</h2>
        <p v-if="bookableServices.length === 0" class="text-neutral-500 text-sm">
          {{ usingPackage ? 'No se encontró el servicio del paquete.' : 'Este profesional no tiene servicios disponibles para reservar.' }}
        </p>
        <div class="grid gap-3 sm:grid-cols-2">
          <AppCard
            v-for="svc in bookableServices"
            :key="svc.id"
            hover
            class="cursor-pointer"
            @click="selectService(svc)"
          >
            <div class="flex justify-between items-start gap-2 mb-2">
              <h3 class="font-semibold text-neutral-900">{{ svc.nombre }}</h3>
              <span class="font-bold text-primary-600 shrink-0">{{ formatPrice(svc.precio) }}</span>
            </div>
            <p v-if="svc.descripcion" class="text-sm text-neutral-500 mb-3 line-clamp-2">{{ svc.descripcion }}</p>
            <div class="flex flex-wrap gap-2 text-xs text-neutral-500">
              <span class="flex items-center gap-1"><Clock class="w-3.5 h-3.5" /> {{ svc.duracion }} min</span>
              <AppBadge variant="primary" size="sm">{{ modalidadLabel(svc.modalidad) }}</AppBadge>
              <AppBadge v-if="svc.type === 'package'" variant="default" size="sm">{{ svc.cantidad_sesiones }} sesiones</AppBadge>
            </div>
          </AppCard>
        </div>
      </div>

      <!-- PASO 2: Fecha y horario -->
      <div v-if="step === 2">
        <div class="flex items-center gap-3 mb-6">
          <button class="text-sm text-primary-600 hover:underline" @click="step = 1">← Cambiar servicio</button>
          <span class="text-neutral-300">|</span>
          <span class="text-sm font-medium text-neutral-700">{{ selectedService?.nombre }}</span>
        </div>

        <h2 class="text-lg font-semibold text-neutral-900 mb-4">Elegí fecha y horario</h2>

        <AppCard class="mb-4">
          <label class="block text-sm font-medium text-neutral-700 mb-2">
            <Calendar class="w-4 h-4 inline mr-1" /> Fecha
          </label>
          <input
            v-model="selectedDate"
            type="date"
            :min="today"
            class="w-full border border-neutral-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
          />
        </AppCard>

        <div v-if="selectedDate">
          <p class="text-sm font-medium text-neutral-700 mb-3">
            Horarios para el {{ formatDateDisplay(selectedDate) }}
          </p>

          <div v-if="slotsLoading" class="flex justify-center py-8">
            <AppSpinner size="md" />
          </div>
          <p v-else-if="sinAgenda" class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 text-center">
            {{ availabilityMessage || 'Este profesional aún no tiene horarios configurados.' }}
          </p>
          <p v-else-if="slots.length === 0" class="text-sm text-neutral-500 text-center py-6">
            No hay horarios disponibles para este día.
          </p>
          <div v-else class="grid grid-cols-3 sm:grid-cols-4 gap-2">
            <button
              v-for="slot in slots"
              :key="slot.start"
              :disabled="!slot.available"
              :class="[
                'py-2 px-3 rounded-lg text-sm font-medium border transition-colors',
                !slot.available
                  ? 'bg-neutral-100 text-neutral-400 border-neutral-200 cursor-not-allowed'
                  : selectedSlot?.start === slot.start
                    ? 'bg-primary-600 text-white border-primary-600'
                    : 'bg-white text-neutral-700 border-neutral-300 hover:border-primary-400 hover:text-primary-600 cursor-pointer'
              ]"
              @click="selectSlot(slot)"
            >
              {{ formatSlotTime(slot.start) }}
            </button>
          </div>
        </div>

        <p v-if="error" class="mt-4 text-sm text-red-600">{{ error }}</p>
      </div>

      <!-- PASO 3: Confirmar -->
      <div v-if="step === 3">
        <div class="flex items-center gap-3 mb-6">
          <button class="text-sm text-primary-600 hover:underline" @click="step = 2">← Cambiar horario</button>
        </div>

        <h2 class="text-lg font-semibold text-neutral-900 mb-4">Confirmá tu reserva</h2>

        <AppCard class="mb-4 divide-y divide-neutral-100">
          <div class="flex justify-between py-3">
            <span class="text-sm text-neutral-500">Profesional</span>
            <span class="text-sm font-medium text-neutral-900">{{ professional.nombre }} {{ professional.apellido }}</span>
          </div>
          <div class="flex justify-between py-3">
            <span class="text-sm text-neutral-500">Servicio</span>
            <span class="text-sm font-medium text-neutral-900">{{ selectedService?.nombre }}</span>
          </div>
          <div class="flex justify-between py-3">
            <span class="text-sm text-neutral-500">Fecha y hora</span>
            <span class="text-sm font-medium text-neutral-900">
              {{ formatDateDisplay(selectedDate) }}, {{ formatSlotTime(selectedSlot?.start) }}
            </span>
          </div>
          <div class="flex justify-between py-3">
            <span class="text-sm text-neutral-500">Duración</span>
            <span class="text-sm font-medium text-neutral-900">{{ selectedService?.duracion }} min</span>
          </div>
          <div class="py-3">
            <p class="text-sm text-neutral-500 mb-2">Modalidad</p>
            <div class="flex gap-2">
              <button
                v-for="m in modalidadOptions"
                :key="m"
                :class="[
                  'flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm border transition-colors cursor-pointer',
                  selectedModalidad === m
                    ? 'bg-primary-600 text-white border-primary-600'
                    : 'bg-white text-neutral-700 border-neutral-300 hover:border-primary-400'
                ]"
                @click="selectedModalidad = m"
              >
                <Video v-if="m === 'virtual'" class="w-3.5 h-3.5" />
                <MapPin v-else class="w-3.5 h-3.5" />
                {{ modalidadLabel(m) }}
              </button>
            </div>
          </div>
          <div v-if="!usingPackage" class="flex justify-between py-3">
            <span class="text-sm text-neutral-500">Total</span>
            <span class="text-lg font-bold text-primary-600">{{ formatPrice(selectedService?.precio) }}</span>
          </div>
          <div v-else class="flex justify-between py-3">
            <span class="text-sm text-neutral-500">Pago</span>
            <span class="text-sm font-medium text-accent-600">Incluido en tu paquete</span>
          </div>
        </AppCard>

        <p v-if="error" class="mb-3 text-sm text-red-600">{{ error }}</p>

        <AppButton
          variant="primary"
          size="lg"
          class="w-full"
          :loading="submitting"
          :disabled="!selectedModalidad"
          @click="confirmBooking"
        >
          {{ usingPackage ? 'Confirmar reserva' : 'Confirmar y pagar' }}
        </AppButton>
        <p v-if="!usingPackage" class="text-xs text-neutral-400 text-center mt-2">
          Serás redirigido a PayPal para completar el pago
        </p>
      </div>
    </template>

    <AppCard v-else class="text-center py-16">
      <p class="text-red-600">{{ error ?? 'Profesional no encontrado.' }}</p>
      <AppButton variant="outline" class="mt-4" as="RouterLink" to="/professionals">Volver al listado</AppButton>
    </AppCard>

  </div>
</template>
