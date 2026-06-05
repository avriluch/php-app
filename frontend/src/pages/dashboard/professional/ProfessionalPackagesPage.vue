<script setup>
import { ref, onMounted } from 'vue'
import { Package } from '@lucide/vue'
import api from '@/services/api'
import AppCard from '@/components/ui/AppCard.vue'
import AppSpinner from '@/components/ui/AppSpinner.vue'
import AppBadge from '@/components/ui/AppBadge.vue'

const loading = ref(true)
const error = ref(null)
const purchases = ref([])

const paymentEstado = {
  pendiente: { label: 'Pago pendiente', variant: 'warning' },
  completado: { label: 'Pagado', variant: 'success' },
  fallido: { label: 'Fallido', variant: 'danger' },
  reembolsado: { label: 'Reembolsado', variant: 'default' },
}

const formatPrice = (n) => new Intl.NumberFormat('es-UY', { style: 'currency', currency: 'UYU' }).format(n)
const formatFecha = (iso) => {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('es-UY', { day: 'numeric', month: 'short', year: 'numeric' })
}

const clientName = (c) => {
  if (!c) return 'Cliente'
  return [c.nombre, c.apellido].filter(Boolean).join(' ') || c.email || 'Cliente'
}

onMounted(async () => {
  loading.value = true
  error.value = null
  try {
    const { data } = await api.get('/professional/package-purchases')
    purchases.value = data.data ?? []
  } catch (e) {
    error.value = e.response?.data?.message ?? 'No se pudieron cargar las compras de paquetes.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div>
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-neutral-900 mb-1">Paquetes vendidos</h1>
      <p class="text-neutral-500 text-sm">Clientes que compraron tus paquetes de sesiones.</p>
    </div>

    <div v-if="loading" class="flex justify-center py-16">
      <AppSpinner size="lg" />
    </div>

    <p v-else-if="error" class="text-red-600 text-sm">{{ error }}</p>

    <AppCard v-else-if="purchases.length === 0" class="text-center py-16">
      <Package class="w-12 h-12 text-neutral-300 mx-auto mb-3" />
      <p class="font-medium text-neutral-700 mb-1">Sin compras de paquetes</p>
      <p class="text-sm text-neutral-500">
        Creá un servicio tipo «paquete» en Servicios para ofrecer múltiples sesiones.
      </p>
    </AppCard>

    <div v-else class="overflow-x-auto">
      <table class="w-full text-sm text-left">
        <thead>
          <tr class="border-b border-neutral-200 text-neutral-500">
            <th class="py-3 pr-4 font-medium">Cliente</th>
            <th class="py-3 pr-4 font-medium">Paquete</th>
            <th class="py-3 pr-4 font-medium">Sesiones</th>
            <th class="py-3 pr-4 font-medium">Pago</th>
            <th class="py-3 font-medium">Fecha</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="p in purchases"
            :key="p.id"
            class="border-b border-neutral-100 last:border-0"
          >
            <td class="py-3 pr-4">
              <p class="font-medium text-neutral-900">{{ clientName(p.cliente) }}</p>
              <p v-if="p.cliente?.email" class="text-xs text-neutral-400">{{ p.cliente.email }}</p>
            </td>
            <td class="py-3 pr-4 text-neutral-800">{{ p.service?.nombre ?? '—' }}</td>
            <td class="py-3 pr-4">
              <span class="font-medium">{{ p.sesiones_restantes }}</span>
              <span class="text-neutral-400"> / {{ p.service?.cantidad_sesiones ?? '?' }}</span>
            </td>
            <td class="py-3 pr-4">
              <div class="flex flex-col gap-1">
                <AppBadge
                  v-if="p.payment"
                  :variant="paymentEstado[p.payment.estado]?.variant ?? 'default'"
                  size="sm"
                >
                  {{ paymentEstado[p.payment.estado]?.label ?? p.payment.estado }}
                </AppBadge>
                <span v-if="p.payment" class="text-xs text-neutral-500">{{ formatPrice(p.payment.monto) }}</span>
              </div>
            </td>
            <td class="py-3 text-neutral-600">{{ formatFecha(p.purchased_at) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
