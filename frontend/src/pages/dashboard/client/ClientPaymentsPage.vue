<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { CreditCard, Receipt } from '@lucide/vue'
import api from '@/services/api'
import AppCard from '@/components/ui/AppCard.vue'
import AppSpinner from '@/components/ui/AppSpinner.vue'
import AppBadge from '@/components/ui/AppBadge.vue'

const loading = ref(true)
const error = ref(null)
const payments = ref([])

const estadoMeta = {
  pendiente: { label: 'Pendiente', variant: 'warning' },
  completado: { label: 'Pagado', variant: 'success' },
  fallido: { label: 'Fallido', variant: 'danger' },
  reembolsado: { label: 'Reembolsado', variant: 'info' },
  cancelado: { label: 'Cancelado', variant: 'danger' },
}
const metodoLabels = { tarjeta_debito: 'Débito', tarjeta_credito: 'Crédito', paypal: 'PayPal' }

const money = (n) =>
  '$ ' + new Intl.NumberFormat('es-UY', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n ?? 0)

const formatFecha = (iso) =>
  iso ? new Date(iso).toLocaleDateString('es-UY', { day: 'numeric', month: 'short', year: 'numeric' }) : '—'

const payLink = (p) =>
  p.tipo === 'reserva'
    ? `/pay/${p.booking_id}/${p.id}`
    : `/pay/package/${p.package_purchase_id}/${p.id}`

async function load() {
  loading.value = true
  error.value = null
  try {
    const { data } = await api.get('/payments')
    payments.value = data.data ?? []
  } catch (e) {
    error.value = e.response?.data?.message ?? 'No se pudieron cargar los pagos.'
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div>
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-neutral-900">Pagos</h1>
      <p class="text-neutral-500 mt-1 text-sm">Historial de pagos de reservas y paquetes.</p>
    </div>

    <div v-if="loading" class="flex justify-center py-16">
      <AppSpinner size="lg" />
    </div>

    <p v-else-if="error" class="text-red-600 text-sm">{{ error }}</p>

    <AppCard v-else-if="payments.length === 0" class="text-center py-16">
      <Receipt class="w-12 h-12 text-neutral-300 mx-auto mb-3" />
      <p class="font-medium text-neutral-700 mb-1">Todavía no tenés pagos</p>
      <p class="text-sm text-neutral-500">Cuando reserves un turno o compres un paquete, aparecerán acá.</p>
    </AppCard>

    <div v-else class="space-y-3">
      <AppCard v-for="p in payments" :key="p.id" padding="md">
        <div class="flex items-center gap-4">
          <div class="w-10 h-10 rounded-xl bg-primary-50 text-primary-600 flex items-center justify-center shrink-0">
            <CreditCard class="w-5 h-5" />
          </div>

          <div class="flex-1 min-w-0">
            <p class="font-medium text-neutral-900 truncate">{{ p.concepto }}</p>
            <p class="text-xs text-neutral-500 mt-0.5">
              {{ formatFecha(p.fecha_pago ?? p.created_at) }}
              <span v-if="p.metodo"> · {{ metodoLabels[p.metodo] ?? p.metodo }}</span>
            </p>
          </div>

          <div class="text-right shrink-0">
            <p class="font-semibold text-neutral-900">{{ money(p.monto) }}</p>
            <AppBadge :variant="(estadoMeta[p.estado] ?? {}).variant ?? 'default'" size="xs" class="mt-1">
              {{ (estadoMeta[p.estado] ?? {}).label ?? p.estado }}
            </AppBadge>
          </div>

          <RouterLink
            v-if="p.estado === 'pendiente'"
            :to="payLink(p)"
            class="shrink-0 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 px-3 py-1.5 rounded-lg no-underline transition-colors"
          >
            Pagar
          </RouterLink>
        </div>
      </AppCard>
    </div>
  </div>
</template>
