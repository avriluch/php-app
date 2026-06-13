<script setup>
import { ref, computed, onMounted } from 'vue'
import { Users, Briefcase, CalendarCheck, Wallet, TrendingUp, UserPlus, Clock } from '@lucide/vue'
import api from '@/services/api'
import AppCard from '@/components/ui/AppCard.vue'
import AppSpinner from '@/components/ui/AppSpinner.vue'
import AppBadge from '@/components/ui/AppBadge.vue'

const loading = ref(true)
const error = ref(null)
const metrics = ref(null)

const money = (n) =>
  '$ ' + new Intl.NumberFormat('es-UY', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(n ?? 0)

const stats = computed(() => {
  const m = metrics.value
  if (!m) return []
  return [
    { label: 'Usuarios registrados', value: m.usuarios.total, hint: `+${m.usuarios.nuevos_mes} este mes`, icon: Users, color: 'text-primary-600 bg-primary-50' },
    { label: 'Profesionales', value: m.profesionales.total, hint: `${m.profesionales.con_servicios} con servicios`, icon: Briefcase, color: 'text-accent-600 bg-accent-50' },
    { label: 'Reservas este mes', value: m.reservas.total_mes, hint: `${m.reservas.canceladas_mes} canceladas`, icon: CalendarCheck, color: 'text-purple-600 bg-purple-50' },
    { label: 'Ingresos del mes', value: money(m.ingresos.mes), hint: `${money(m.ingresos.total)} histórico`, icon: Wallet, color: 'text-emerald-600 bg-emerald-50' },
  ]
})

const roleBreakdown = computed(() => {
  const r = metrics.value?.usuarios.por_rol
  if (!r) return []
  const total = (r.client ?? 0) + (r.professional ?? 0) + (r.admin ?? 0)
  return [
    { label: 'Clientes', value: r.client ?? 0, color: 'bg-blue-500' },
    { label: 'Profesionales', value: r.professional ?? 0, color: 'bg-primary-500' },
    { label: 'Admins', value: r.admin ?? 0, color: 'bg-red-500' },
  ].map((x) => ({ ...x, pct: total ? Math.round((x.value / total) * 100) : 0 }))
})

const statusMeta = {
  pendiente: { label: 'Pendiente', variant: 'warning' },
  confirmada: { label: 'Confirmada', variant: 'info' },
  pagada: { label: 'Pagada', variant: 'paid' },
  en_curso: { label: 'En curso', variant: 'primary' },
  finalizada: { label: 'Finalizada', variant: 'success' },
  cancelada: { label: 'Cancelada', variant: 'danger' },
  no_asistida: { label: 'No asistió', variant: 'default' },
}

const bookingsByStatus = computed(() => {
  const porEstado = metrics.value?.reservas.por_estado_mes ?? {}
  return Object.entries(porEstado).map(([estado, total]) => ({
    estado,
    total,
    label: statusMeta[estado]?.label ?? estado,
    variant: statusMeta[estado]?.variant ?? 'default',
  }))
})

// ── Actividad reciente ────────────────────────────────────────────
const activity = ref([])
const activityLoading = ref(true)

const activityMeta = {
  usuario: { icon: UserPlus, color: 'text-blue-600 bg-blue-50' },
  reserva: { icon: CalendarCheck, color: 'text-primary-600 bg-primary-50' },
  pago: { icon: Wallet, color: 'text-emerald-600 bg-emerald-50' },
}

const formatRelative = (iso) => {
  if (!iso) return ''
  const diff = Date.now() - new Date(iso).getTime()
  const min = Math.floor(diff / 60000)
  if (min < 1) return 'recién'
  if (min < 60) return `hace ${min} min`
  const h = Math.floor(min / 60)
  if (h < 24) return `hace ${h} h`
  const d = Math.floor(h / 24)
  if (d < 30) return `hace ${d} día${d === 1 ? '' : 's'}`
  return new Date(iso).toLocaleDateString('es-UY', { day: 'numeric', month: 'short' })
}

async function load() {
  loading.value = true
  error.value = null
  try {
    const { data } = await api.get('/admin/metrics')
    metrics.value = data
  } catch (e) {
    error.value = e.response?.data?.message ?? 'No se pudieron cargar las métricas.'
  } finally {
    loading.value = false
  }
}

async function loadActivity() {
  activityLoading.value = true
  try {
    const { data } = await api.get('/admin/activity')
    activity.value = data.data ?? []
  } catch {
    activity.value = []
  } finally {
    activityLoading.value = false
  }
}

onMounted(() => {
  load()
  loadActivity()
})
</script>

<template>
  <div>
    <div class="mb-8">
      <h1 class="text-2xl font-bold text-neutral-900">Panel administrativo</h1>
      <p class="text-neutral-500 mt-1">Métricas y actividad del sistema.</p>
    </div>

    <div v-if="loading" class="flex justify-center py-16">
      <AppSpinner size="lg" />
    </div>

    <p v-else-if="error" class="text-red-600 text-sm">{{ error }}</p>

    <template v-else>
      <!-- Tarjetas -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <AppCard v-for="stat in stats" :key="stat.label" padding="sm">
          <div class="flex items-center gap-3">
            <div :class="['w-9 h-9 rounded-xl flex items-center justify-center shrink-0', stat.color]">
              <component :is="stat.icon" class="w-4 h-4" />
            </div>
            <div class="min-w-0">
              <p class="text-xl font-bold text-neutral-900 truncate">{{ stat.value }}</p>
              <p class="text-xs text-neutral-500">{{ stat.label }}</p>
              <p class="text-[11px] text-neutral-400 mt-0.5">{{ stat.hint }}</p>
            </div>
          </div>
        </AppCard>
      </div>

      <div class="grid lg:grid-cols-2 gap-4">
        <!-- Usuarios por rol -->
        <AppCard>
          <h2 class="font-semibold text-neutral-900 mb-4">Usuarios por rol</h2>
          <div class="space-y-3">
            <div v-for="r in roleBreakdown" :key="r.label">
              <div class="flex justify-between text-sm mb-1">
                <span class="text-neutral-600">{{ r.label }}</span>
                <span class="font-medium text-neutral-900">{{ r.value }} · {{ r.pct }}%</span>
              </div>
              <div class="h-2 bg-neutral-100 rounded-full overflow-hidden">
                <div :class="['h-full rounded-full', r.color]" :style="{ width: r.pct + '%' }" />
              </div>
            </div>
          </div>
        </AppCard>

        <!-- Reservas por estado -->
        <AppCard>
          <h2 class="font-semibold text-neutral-900 mb-4">Reservas del mes por estado</h2>
          <div v-if="bookingsByStatus.length === 0" class="flex flex-col items-center justify-center py-8 text-center">
            <TrendingUp class="w-8 h-8 text-neutral-300 mb-2" />
            <p class="text-sm text-neutral-500">Todavía no hay reservas este mes.</p>
          </div>
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

      <!-- Actividad reciente -->
      <AppCard class="mt-4">
        <h2 class="font-semibold text-neutral-900 mb-4 flex items-center gap-2">
          <Clock class="w-4 h-4 text-neutral-400" /> Actividad reciente
        </h2>

        <div v-if="activityLoading" class="flex justify-center py-8">
          <AppSpinner />
        </div>

        <p v-else-if="activity.length === 0" class="text-sm text-neutral-500 text-center py-8">
          Todavía no hay actividad para mostrar.
        </p>

        <ul v-else class="divide-y divide-neutral-50">
          <li v-for="(item, i) in activity" :key="i" class="flex items-center gap-3 py-2.5">
            <div
              :class="['w-8 h-8 rounded-lg flex items-center justify-center shrink-0', (activityMeta[item.tipo] ?? activityMeta.usuario).color]"
            >
              <component :is="(activityMeta[item.tipo] ?? activityMeta.usuario).icon" class="w-4 h-4" />
            </div>
            <p class="flex-1 text-sm text-neutral-700 min-w-0 truncate">{{ item.descripcion }}</p>
            <span class="text-xs text-neutral-400 shrink-0">{{ formatRelative(item.fecha) }}</span>
          </li>
        </ul>
      </AppCard>
    </template>
  </div>
</template>
