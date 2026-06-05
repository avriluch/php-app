<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { CheckCircle, AlertCircle } from '@lucide/vue'
import api from '@/services/api'
import AppCard from '@/components/ui/AppCard.vue'
import AppSpinner from '@/components/ui/AppSpinner.vue'
import AppButton from '@/components/ui/AppButton.vue'

const route = useRoute()
const router = useRouter()

const isPackagePayment = computed(() => route.name === 'payment-package')

const loading = ref(true)
const paypalLoading = ref(false)
const error = ref(null)
const success = ref(false)
const payment = ref(null)
const booking = ref(null)
const packagePurchase = ref(null)

const paypalClientId = (import.meta.env.VITE_PAYPAL_CLIENT_ID ?? '').trim()
const paypalConfigured = Boolean(paypalClientId)

const successRedirect = computed(() =>
  isPackagePayment.value ? '/dashboard/client/packages' : '/dashboard/client/bookings',
)

const successMessage = computed(() =>
  isPackagePayment.value
    ? 'Tu paquete quedó activo. Ya podés reservar sesiones desde Mis paquetes.'
    : 'Tu reserva ha sido confirmada y pagada exitosamente.',
)

async function loadPayment() {
  try {
    if (isPackagePayment.value) {
      const { data } = await api.get(`/package-purchases/${route.params.purchaseId}`)
      packagePurchase.value = data
      const paymentId = Number(route.params.paymentId)
      if (data.payment?.id !== paymentId) {
        error.value = 'El pago no coincide con este paquete.'
        return
      }
      payment.value = data.payment
    } else {
      const { data } = await api.get(`/bookings/${route.params.bookingId}`)
      booking.value = data
      payment.value = data.payment
    }
  } catch {
    error.value = 'No se pudo cargar la información del pago.'
  } finally {
    loading.value = false
  }
}

function loadPayPalScript() {
  return new Promise((resolve, reject) => {
    if (!paypalConfigured) {
      reject(new Error('PAYPAL_NOT_CONFIGURED'))
      return
    }
    if (window.paypal) return resolve()
    const script = document.createElement('script')
    script.src = `https://www.paypal.com/sdk/js?client-id=${paypalClientId}&currency=USD&disable-funding=card,credit`
    script.onload = resolve
    script.onerror = reject
    document.head.appendChild(script)
  })
}

function formatPrice(amount) {
  return new Intl.NumberFormat('es-UY', { style: 'currency', currency: 'USD' }).format(amount)
}

async function renderPayPalButtons() {
  await loadPayPalScript()

  window.paypal.Buttons({
    style: {
      layout: 'vertical',
      color: 'blue',
      shape: 'rect',
      label: 'pay',
    },

    async createOrder() {
      paypalLoading.value = true
      error.value = null
      try {
        const { data } = await api.post(`/payments/${payment.value.id}/paypal/create-order`)
        return data.order_id
      } catch (e) {
        error.value = e.response?.data?.message ?? 'Error al iniciar el pago.'
        paypalLoading.value = false
        throw e
      }
    },

    async onApprove(data) {
      try {
        await api.post(`/payments/${payment.value.id}/paypal/capture`, {
          order_id: data.orderID,
        })
        success.value = true
      } catch (e) {
        error.value = e.response?.data?.message ?? 'Error al confirmar el pago.'
      } finally {
        paypalLoading.value = false
      }
    },

    onError() {
      error.value = 'Ocurrió un error en PayPal. Intentá de nuevo.'
      paypalLoading.value = false
    },

    onCancel() {
      paypalLoading.value = false
    },
  }).render('#paypal-buttons')
}

onMounted(async () => {
  await loadPayment()
  if (!payment.value || payment.value.estado !== 'pendiente') return
  if (!paypalConfigured) {
    error.value =
      'PayPal Sandbox no está configurado. Agregá VITE_PAYPAL_CLIENT_ID en frontend/.env y reiniciá npm run dev.'
    return
  }
  try {
    await renderPayPalButtons()
  } catch {
    error.value =
      'No se pudo cargar el botón de PayPal. Revisá el Client ID en frontend/.env y las credenciales en backend/.env.'
  }
})
</script>

<template>
  <div class="max-w-lg mx-auto px-4 sm:px-6 py-10">

    <div v-if="loading" class="flex justify-center py-20">
      <AppSpinner size="lg" />
    </div>

    <AppCard v-else-if="success" class="text-center py-12">
      <CheckCircle class="w-16 h-16 text-accent-500 mx-auto mb-4" />
      <h1 class="text-2xl font-bold text-neutral-900 mb-2">¡Pago completado!</h1>
      <p class="text-neutral-500 mb-6">{{ successMessage }}</p>
      <AppButton variant="primary" @click="router.push(successRedirect)">
        {{ isPackagePayment ? 'Ver mis paquetes' : 'Ver mis reservas' }}
      </AppButton>
    </AppCard>

    <AppCard v-else-if="payment?.estado !== 'pendiente'" class="text-center py-10">
      <CheckCircle class="w-12 h-12 text-accent-500 mx-auto mb-3" />
      <h2 class="font-semibold text-neutral-900 mb-1">Este pago ya fue procesado</h2>
      <p class="text-sm text-neutral-500 mb-4">Estado: {{ payment?.estado }}</p>
      <AppButton variant="outline" @click="router.push(successRedirect)">
        {{ isPackagePayment ? 'Ver mis paquetes' : 'Ver mis reservas' }}
      </AppButton>
    </AppCard>

    <template v-else>
      <h1 class="text-2xl font-bold text-neutral-900 mb-2">Completar pago</h1>
      <p v-if="isPackagePayment" class="text-neutral-500 mb-6">
        Paquete: {{ packagePurchase?.service?.nombre }}
        <span v-if="packagePurchase?.profesional">
          · {{ packagePurchase.profesional.nombre }} {{ packagePurchase.profesional.apellido }}
        </span>
      </p>
      <p v-else class="text-neutral-500 mb-6">
        Reserva con {{ booking?.professional?.nombre }} {{ booking?.professional?.apellido }}
      </p>

      <AppCard class="mb-6">
        <div class="flex justify-between items-center">
          <div>
            <p class="font-medium text-neutral-900">
              {{ isPackagePayment ? packagePurchase?.service?.nombre : booking?.service?.nombre }}
            </p>
            <p class="text-sm text-neutral-500">
              <template v-if="isPackagePayment">
                {{ packagePurchase?.service?.cantidad_sesiones }} sesiones
              </template>
              <template v-else>
                {{ booking?.service?.duracion }} min · {{ booking?.modalidad }}
              </template>
            </p>
          </div>
          <p class="text-xl font-bold text-primary-600">{{ formatPrice(payment?.monto) }}</p>
        </div>
      </AppCard>

      <AppCard>
        <p class="text-sm font-medium text-neutral-700 mb-4">Pagá con PayPal (Sandbox)</p>

        <div
          v-if="!paypalConfigured"
          class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"
        >
          <p class="font-medium mb-1">Falta configurar PayPal</p>
          <p>
            Agregá <code class="text-xs bg-amber-100 px-1 rounded">VITE_PAYPAL_CLIENT_ID</code>
            en <code class="text-xs bg-amber-100 px-1 rounded">frontend/.env</code>
            y reiniciá <code class="text-xs bg-amber-100 px-1 rounded">npm run dev</code>.
          </p>
        </div>

        <template v-else>
          <div v-if="paypalLoading" class="flex justify-center py-4">
            <AppSpinner size="md" />
          </div>

          <div id="paypal-buttons" class="min-h-[45px]" />

          <p v-if="error" class="mt-3 text-sm text-red-600 flex items-center gap-1">
            <AlertCircle class="w-4 h-4 shrink-0" /> {{ error }}
          </p>
        </template>
      </AppCard>
    </template>

  </div>
</template>
