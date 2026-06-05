<script setup>
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { Users, Search, Calendar, Package, Mail, Phone } from '@lucide/vue'
import api from '@/services/api'
import AppCard from '@/components/ui/AppCard.vue'
import AppSpinner from '@/components/ui/AppSpinner.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppAvatar from '@/components/ui/AppAvatar.vue'

const loading = ref(true)
const error = ref(null)
const clients = ref([])
const search = ref('')

const filteredClients = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return clients.value
  return clients.value.filter((c) => {
    const text = [c.nombre, c.apellido, c.email, c.telefono].filter(Boolean).join(' ').toLowerCase()
    return text.includes(q)
  })
})

const clientName = (c) => [c.nombre, c.apellido].filter(Boolean).join(' ') || c.email || 'Cliente'

const formatFecha = (iso) => {
  if (!iso) return null
  return new Date(iso).toLocaleDateString('es-UY', { day: 'numeric', month: 'short', year: 'numeric' })
}

const formatFechaHora = (iso) => {
  if (!iso) return null
  return new Date(iso).toLocaleString('es-UY', {
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  })
}

async function load() {
  loading.value = true
  error.value = null
  try {
    const { data } = await api.get('/professional/clients')
    clients.value = data.data ?? []
  } catch (e) {
    error.value = e.response?.data?.message ?? 'No se pudieron cargar los clientes.'
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
      <div>
        <h1 class="text-2xl font-bold text-neutral-900 mb-1">Clientes</h1>
        <p class="text-neutral-500 text-sm">Personas que reservaron o compraron paquetes con vos.</p>
      </div>
      <div v-if="!loading && clients.length > 0" class="relative w-full sm:w-72">
        <Search class="w-4 h-4 text-neutral-400 absolute left-3 top-1/2 -translate-y-1/2" />
        <input
          v-model="search"
          type="search"
          placeholder="Buscar por nombre o email..."
          class="w-full pl-9 pr-3 py-2 text-sm border border-neutral-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500"
        />
      </div>
    </div>

    <div v-if="loading" class="flex justify-center py-16">
      <AppSpinner size="lg" />
    </div>

    <p v-else-if="error" class="text-red-600 text-sm">{{ error }}</p>

    <AppCard v-else-if="clients.length === 0" class="text-center py-16">
      <Users class="w-12 h-12 text-neutral-300 mx-auto mb-3" />
      <p class="font-medium text-neutral-700 mb-1">Todavía no tenés clientes</p>
      <p class="text-sm text-neutral-500 mb-6">
        Cuando alguien reserve un turno o compre un paquete, aparecerá acá.
      </p>
      <RouterLink
        to="/dashboard/professional/bookings"
        class="text-sm font-medium text-primary-600 hover:underline"
      >
        Ver mis reservas
      </RouterLink>
    </AppCard>

    <p v-else-if="filteredClients.length === 0" class="text-sm text-neutral-500 text-center py-8">
      Ningún cliente coincide con la búsqueda.
    </p>

    <div v-else class="space-y-3">
      <AppCard v-for="c in filteredClients" :key="c.id" padding="md">
        <div class="flex flex-col sm:flex-row gap-4">
          <AppAvatar :name="clientName(c)" :src="c.foto_perfil" size="lg" class="shrink-0" />

          <div class="flex-1 min-w-0">
            <div class="flex flex-wrap items-start justify-between gap-2">
              <div>
                <h3 class="font-semibold text-neutral-900">{{ clientName(c) }}</h3>
                <p v-if="c.email" class="text-sm text-neutral-500 flex items-center gap-1 mt-0.5">
                  <Mail class="w-3.5 h-3.5 shrink-0" /> {{ c.email }}
                </p>
                <p v-if="c.telefono" class="text-sm text-neutral-500 flex items-center gap-1 mt-0.5">
                  <Phone class="w-3.5 h-3.5 shrink-0" /> {{ c.telefono }}
                </p>
              </div>
              <div class="flex flex-wrap gap-2">
                <AppBadge v-if="c.total_reservas > 0" variant="primary" size="sm">
                  {{ c.total_reservas }} reserva{{ c.total_reservas === 1 ? '' : 's' }}
                </AppBadge>
                <AppBadge v-if="c.total_paquetes > 0" variant="accent" size="sm">
                  {{ c.total_paquetes }} paquete{{ c.total_paquetes === 1 ? '' : 's' }}
                </AppBadge>
                <AppBadge
                  v-if="c.sesiones_paquete_restantes > 0"
                  variant="success"
                  size="sm"
                >
                  {{ c.sesiones_paquete_restantes }} sesión(es) de paquete
                </AppBadge>
              </div>
            </div>

            <div class="flex flex-wrap gap-x-4 gap-y-1 mt-3 text-xs text-neutral-500">
              <span v-if="c.ultima_reserva" class="flex items-center gap-1">
                <Calendar class="w-3.5 h-3.5" />
                Última reserva: {{ formatFecha(c.ultima_reserva) }}
              </span>
              <span v-if="c.proxima_reserva" class="flex items-center gap-1 text-primary-600 font-medium">
                <Calendar class="w-3.5 h-3.5" />
                Próximo turno: {{ formatFechaHora(c.proxima_reserva) }}
              </span>
              <span v-if="c.ultima_compra_paquete && !c.ultima_reserva" class="flex items-center gap-1">
                <Package class="w-3.5 h-3.5" />
                Compró paquete: {{ formatFecha(c.ultima_compra_paquete) }}
              </span>
            </div>
          </div>
        </div>
      </AppCard>
    </div>

    <p v-if="!loading && clients.length > 0" class="text-xs text-neutral-400 mt-4 text-center">
      {{ filteredClients.length }} de {{ clients.length }} cliente{{ clients.length === 1 ? '' : 's' }}
    </p>
  </div>
</template>
