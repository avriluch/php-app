<script setup>
import { ref, computed, onMounted, watch, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { CheckCircle, AlertCircle, CreditCard } from '@lucide/vue'
import api from '@/services/api'
import AppCard from '@/components/ui/AppCard.vue'
import AppSpinner from '@/components/ui/AppSpinner.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppInput from '@/components/ui/AppInput.vue'

const route = useRoute()
const router = useRouter()

const isPackagePayment = computed(() => route.name === 'payment-package')

const loading = ref(true)
const paypalLoading = ref(false)
const cardSubmitting = ref(false)
const error = ref(null)
const cardError = ref(null)
const success = ref(false)
const payment = ref(null)
const booking = ref(null)
const packagePurchase = ref(null)

const paymentMethod = ref('card')

const cardForm = ref({
  metodo: 'tarjeta_credito',
  numero: '',
  titular: '',
  vencimiento: '',
  cvv: '',
})

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

const money = computed(() => {
  const amount = payment.value?.monto ?? 0
  return new Intl.NumberFormat('es-UY', { style: 'currency', currency: 'UYU' }).format(amount)
})

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

async function renderPayPalButtons() {
  const container = document.getElementById('paypal-buttons')
  if (!container) return
  container.innerHTML = ''

  await loadPayPalScript()

  window.paypal.Buttons({
    style: { layout: 'vertical', color: 'blue', shape: 'rect', label: 'pay' },

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
        await api.post(`/payments/${payment.value.id}/paypal/capture`, { order_id: data.orderID })
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

async function submitCardPayment() {
  cardError.value = null
  cardSubmitting.value = true

  try {
    await api.post(`/payments/${payment.value.id}/card`, {
      metodo: cardForm.value.metodo,
      numero: cardForm.value.numero.replace(/\s/g, ''),
      titular: cardForm.value.titular.trim(),
      vencimiento: cardForm.value.vencimiento.trim(),
      cvv: cardForm.value.cvv.trim(),
    })
    success.value = true
  } catch (e) {
    const data = e.response?.data
    if (data?.errors) {
      cardError.value = Object.values(data.errors).flat().join(' ')
    } else {
      cardError.value = data?.message ?? 'No se pudo procesar el pago.'
    }
  } finally {
    cardSubmitting.value = false
  }
}

function formatCardNumber(value) {
  const digits = value.replace(/\D/g, '').slice(0, 19)
  return digits.replace(/(\d{4})(?=\d)/g, '$1 ').trim()
}

function onCardNumberUpdate(value) {
  cardForm.value.numero = formatCardNumber(String(value))
}

function onExpiryUpdate(value) {
  let v = String(value).replace(/\D/g, '').slice(0, 6)
  if (v.length >= 3) v = `${v.slice(0, 2)}/${v.slice(2)}`
  cardForm.value.vencimiento = v
}

watch(paymentMethod, async (method) => {
  error.value = null
  cardError.value = null
  if (method === 'paypal' && paypalConfigured && payment.value?.estado === 'pendiente') {
    await nextTick()
    try {
      await renderPayPalButtons()
    } catch {
      error.value = 'No se pudo cargar PayPal.'
    }
  }
})

onMounted(async () => {
  await loadPayment()
  if (!payment.value || payment.value.estado !== 'pendiente') return

  if (!paypalConfigured) {
    paymentMethod.value = 'card'
    return
  }

  if (paymentMethod.value === 'paypal') {
    try {
      await nextTick()
      await renderPayPalButtons()
    } catch {
      paymentMethod.value = 'card'
    }
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
          <p class="text-xl font-bold text-primary-600">{{ money }}</p>
        </div>
      </AppCard>

      <!-- Selector de método -->
      <div class="flex gap-2 mb-4">
        <button
          type="button"
          class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium rounded-lg border transition-colors cursor-pointer"
          :class="paymentMethod === 'card'
            ? 'border-primary-500 bg-primary-50 text-primary-700'
            : 'border-neutral-300 bg-white text-neutral-600 hover:bg-neutral-50'"
          @click="paymentMethod = 'card'"
        >
          <CreditCard class="w-4 h-4" /> Tarjeta
        </button>
        <button
          v-if="paypalConfigured"
          type="button"
          class="flex-1 px-4 py-2.5 text-sm font-medium rounded-lg border transition-colors cursor-pointer"
          :class="paymentMethod === 'paypal'
            ? 'border-primary-500 bg-primary-50 text-primary-700'
            : 'border-neutral-300 bg-white text-neutral-600 hover:bg-neutral-50'"
          @click="paymentMethod = 'paypal'"
        >
          PayPal
        </button>
      </div>

      <!-- Pago con tarjeta simulado -->
      <AppCard v-if="paymentMethod === 'card'">
        <p class="text-sm font-medium text-neutral-700 mb-4">Tarjeta de débito o crédito (simulado)</p>

        <form class="space-y-4" @submit.prevent="submitCardPayment">
          <div class="flex gap-2">
            <button
              v-for="opt in [{ value: 'tarjeta_credito', label: 'Crédito' }, { value: 'tarjeta_debito', label: 'Débito' }]"
              :key="opt.value"
              type="button"
              class="px-3 py-1.5 text-sm rounded-full border transition-colors cursor-pointer"
              :class="cardForm.metodo === opt.value
                ? 'bg-primary-600 border-primary-600 text-white'
                : 'border-neutral-300 text-neutral-600 hover:border-primary-400'"
              @click="cardForm.metodo = opt.value"
            >
              {{ opt.label }}
            </button>
          </div>

          <AppInput
            id="card-number"
            label="Número de tarjeta"
            :model-value="cardForm.numero"
            placeholder="4111 1111 1111 1111"
            required
            @update:model-value="onCardNumberUpdate"
          />

          <AppInput
            id="card-holder"
            v-model="cardForm.titular"
            label="Titular"
            placeholder="Como figura en la tarjeta"
            autocomplete="cc-name"
            required
          />

          <div class="grid grid-cols-2 gap-3">
            <AppInput
              id="card-expiry"
              label="Vencimiento"
              :model-value="cardForm.vencimiento"
              placeholder="MM/AA"
              required
              @update:model-value="onExpiryUpdate"
            />
            <AppInput
              id="card-cvv"
              v-model="cardForm.cvv"
              label="CVV"
              type="password"
              placeholder="123"
              required
            />
          </div>

          <p class="text-xs text-neutral-500 rounded-lg bg-neutral-50 border border-neutral-100 p-3">
            <strong>Pruebas:</strong> <code class="text-[11px]">4111111111111111</code> aprueba ·
            <code class="text-[11px]">4000000000000002</code> rechaza. No se guardan datos reales de tarjeta.
          </p>

          <p v-if="cardError" class="text-sm text-red-600 flex items-start gap-1">
            <AlertCircle class="w-4 h-4 shrink-0 mt-0.5" /> {{ cardError }}
          </p>

          <AppButton type="submit" variant="primary" class="w-full" :loading="cardSubmitting">
            Pagar {{ money }}
          </AppButton>
        </form>
      </AppCard>

      <!-- PayPal -->
      <AppCard v-else>
        <p class="text-sm font-medium text-neutral-700 mb-4">Pagá con PayPal (Sandbox)</p>

        <div v-if="paypalLoading" class="flex justify-center py-4">
          <AppSpinner size="md" />
        </div>

        <div id="paypal-buttons" class="min-h-[45px]" />

        <p v-if="error" class="mt-3 text-sm text-red-600 flex items-center gap-1">
          <AlertCircle class="w-4 h-4 shrink-0" /> {{ error }}
        </p>
      </AppCard>
    </template>

  </div>
</template>
