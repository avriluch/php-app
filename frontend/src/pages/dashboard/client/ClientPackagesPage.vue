<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { Package, Calendar, CreditCard, Search } from '@lucide/vue'
import api from '@/services/api'
import AppCard from '@/components/ui/AppCard.vue'
import AppSpinner from '@/components/ui/AppSpinner.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppBadge from '@/components/ui/AppBadge.vue'

const router = useRouter()
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

const profName = (p) => {
  const pr = p.profesional
  if (!pr) return 'Profesional'
  return [pr.nombre, pr.apellido].filter(Boolean).join(' ')
}

const canReserve = (p) =>
  p.payment?.estado === 'completado' && p.sesiones_restantes > 0

onMounted(async () => {
  loading.value = true
  error.value = null
  try {
    const { data } = await api.get('/package-purchases')
    purchases.value = data.data ?? []
  } catch (e) {
    error.value = e.response?.data?.message ?? 'No se pudieron cargar tus paquetes.'
  } finally {
    loading.value = false
  }
})

function goPay(p) {
  if (!p.payment?.id) return
  router.push(`/pay/package/${p.id}/${p.payment.id}`)
}

function goReserve(p) {
  const profId = p.profesional?.id
  if (!profId) return
  router.push({
    path: `/book/${profId}`,
    query: { package_purchase_id: String(p.id) },
  })
}
</script>

<template>
  <div>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
      <div>
        <h1 class="text-2xl font-bold text-neutral-900 mb-1">Mis paquetes</h1>
        <p class="text-neutral-500 text-sm">Paquetes comprados y sesiones disponibles.</p>
      </div>
      <AppButton variant="primary" size="sm" as="RouterLink" to="/professionals?type=package">
        <Search class="w-4 h-4" /> Explorar paquetes
      </AppButton>
    </div>

    <div v-if="loading" class="flex justify-center py-16">
      <AppSpinner size="lg" />
    </div>

    <p v-else-if="error" class="text-red-600 text-sm">{{ error }}</p>

    <AppCard v-else-if="purchases.length === 0" class="text-center py-16">
      <Package class="w-12 h-12 text-neutral-300 mx-auto mb-3" />
      <p class="font-medium text-neutral-700 mb-1">No tenés paquetes todavía</p>
      <p class="text-sm text-neutral-500 mb-6">
        Buscá profesionales que ofrezcan paquetes de sesiones y comprá uno desde su perfil.
      </p>
      <AppButton variant="primary" as="RouterLink" to="/professionals">Buscar profesionales</AppButton>
    </AppCard>

    <div v-else class="space-y-3">
      <AppCard v-for="p in purchases" :key="p.id" padding="md">
        <div class="flex flex-col sm:flex-row sm:items-start gap-4 justify-between">
          <div class="flex-1 min-w-0">
            <h3 class="font-semibold text-neutral-900">{{ p.service?.nombre ?? 'Paquete' }}</h3>
            <p class="text-sm text-neutral-500 mt-0.5">con {{ profName(p) }}</p>
            <p class="text-xs text-neutral-400 mt-1">Comprado el {{ formatFecha(p.purchased_at) }}</p>

            <div class="flex flex-wrap gap-2 mt-3">
              <AppBadge variant="primary" size="sm">
                {{ p.sesiones_restantes }} / {{ p.service?.cantidad_sesiones ?? '?' }} sesiones restantes
              </AppBadge>
              <AppBadge
                v-if="p.payment"
                :variant="paymentEstado[p.payment.estado]?.variant ?? 'default'"
                size="sm"
              >
                {{ paymentEstado[p.payment.estado]?.label ?? p.payment.estado }}
              </AppBadge>
            </div>
          </div>

          <div class="flex flex-col gap-2 shrink-0 sm:items-end">
            <span v-if="p.payment" class="text-sm font-medium text-neutral-700">
              {{ formatPrice(p.payment.monto) }}
            </span>
            <AppButton
              v-if="p.payment?.estado === 'pendiente'"
              variant="primary"
              size="sm"
              @click="goPay(p)"
            >
              <CreditCard class="w-4 h-4" /> Pagar con PayPal
            </AppButton>
            <AppButton
              v-else-if="canReserve(p)"
              variant="outline"
              size="sm"
              @click="goReserve(p)"
            >
              <Calendar class="w-4 h-4" /> Reservar sesión
            </AppButton>
            <p
              v-else-if="p.payment?.estado === 'completado' && p.sesiones_restantes === 0"
              class="text-xs text-neutral-500"
            >
              Sin sesiones restantes
            </p>
          </div>
        </div>
      </AppCard>
    </div>
  </div>
</template>
