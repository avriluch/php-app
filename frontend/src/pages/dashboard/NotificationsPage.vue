<script setup>
import { ref, onMounted } from 'vue'
import { CheckCheck, Bell, Calendar, AlertCircle, Check } from '@lucide/vue'
import api from '@/services/api'
import AppCard from '@/components/ui/AppCard.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppSpinner from '@/components/ui/AppSpinner.vue'
import { useNotificationsStore } from '@/stores/notifications'

const store = useNotificationsStore()

const items = ref([])
const meta = ref({ current_page: 1, last_page: 1, total: 0, unread_count: 0 })
const cargando = ref(false)
const error = ref(null)
const pagina = ref(1)
const soloSinLeer = ref(false)

async function cargar() {
  cargando.value = true
  error.value = null
  try {
    const { data } = await api.get('/notifications', {
      params: {
        page: pagina.value,
        per_page: 20,
        unread: soloSinLeer.value ? 1 : 0,
      },
    })
    items.value = data.data ?? []
    meta.value = data.meta ?? meta.value
  } catch (e) {
    error.value = e.response?.data?.message ?? 'No se pudieron cargar las notificaciones.'
  } finally {
    cargando.value = false
  }
}

function irPagina(p) {
  if (p < 1 || p > meta.value.last_page) return
  pagina.value = p
  cargar()
}

function alternarFiltro() {
  soloSinLeer.value = !soloSinLeer.value
  pagina.value = 1
  cargar()
}

async function marcarLeida(item) {
  if (item.read_at) return
  try {
    const { data } = await api.patch(`/notifications/${item.id}/read`)
    item.read_at = data.read_at
    meta.value.unread_count = Math.max(0, meta.value.unread_count - 1)
    // Mantener sincronizado el badge del header.
    if (store.unreadCount > 0) store.unreadCount--
    const enStore = store.items.find((n) => n.id === item.id)
    if (enStore) enStore.read_at = data.read_at
  } catch (e) {
    error.value = e.response?.data?.message ?? 'No se pudo marcar la notificación.'
  }
}

async function marcarTodas() {
  await store.marcarTodasLeidas()
  meta.value.unread_count = 0
  const ahora = new Date().toISOString()
  items.value = items.value.map((n) => (n.read_at ? n : { ...n, read_at: ahora }))
}

function tiempoRelativo(iso) {
  if (!iso) return ''
  const fecha = new Date(iso)
  return fecha.toLocaleString('es-UY', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function iconoPorTipo(tipo) {
  if (tipo === 'cancelacion') return AlertCircle
  if (tipo === 'recordatorio') return Calendar
  return Check
}

function colorPorTipo(tipo) {
  if (tipo === 'cancelacion') return 'text-red-500 bg-red-50'
  if (tipo === 'recordatorio') return 'text-amber-500 bg-amber-50'
  return 'text-green-500 bg-green-50'
}

function etiquetaTipo(tipo) {
  return (
    { confirmacion: 'Confirmación', recordatorio: 'Recordatorio', cancelacion: 'Cancelación' }[
      tipo
    ] ?? tipo
  )
}

onMounted(cargar)
</script>

<template>
  <div class="max-w-3xl">
    <header class="mb-6 flex items-start justify-between gap-4 flex-wrap">
      <div>
        <h1 class="text-2xl font-bold text-neutral-900 flex items-center gap-2">
          <Bell class="w-6 h-6 text-primary-600" /> Notificaciones
        </h1>
        <p class="text-sm text-neutral-500 mt-1">
          {{ meta.total }} en total · {{ meta.unread_count }} sin leer
        </p>
      </div>
      <div class="flex gap-2">
        <AppButton variant="outline" size="sm" @click="alternarFiltro">
          {{ soloSinLeer ? 'Ver todas' : 'Solo sin leer' }}
        </AppButton>
        <AppButton
          variant="secondary"
          size="sm"
          :disabled="!meta.unread_count"
          @click="marcarTodas"
        >
          <CheckCheck class="w-4 h-4" /> Marcar todas
        </AppButton>
      </div>
    </header>

    <div
      v-if="error"
      class="mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-sm text-red-700"
    >
      {{ error }}
    </div>

    <div v-if="cargando" class="flex justify-center py-12">
      <AppSpinner size="lg" />
    </div>

    <AppCard v-else-if="!items.length" padding="lg" class="text-center">
      <p class="text-neutral-500">
        {{ soloSinLeer ? 'No tenés notificaciones sin leer.' : 'Sin notificaciones todavía.' }}
      </p>
    </AppCard>

    <ul v-else class="space-y-2">
      <li
        v-for="n in items"
        :key="n.id"
        :class="[
          'flex items-start gap-3 px-4 py-3 rounded-xl border transition-colors cursor-pointer',
          n.read_at
            ? 'bg-white border-neutral-200 hover:bg-neutral-50'
            : 'bg-primary-50/40 border-primary-200 hover:bg-primary-50',
        ]"
        @click="marcarLeida(n)"
      >
        <span
          :class="['shrink-0 w-8 h-8 rounded-lg flex items-center justify-center', colorPorTipo(n.tipo)]"
        >
          <component :is="iconoPorTipo(n.tipo)" class="w-4 h-4" />
        </span>
        <div class="flex-1 min-w-0">
          <div class="flex items-start justify-between gap-2 flex-wrap">
            <p
              :class="[
                'text-sm leading-snug',
                n.read_at ? 'text-neutral-700' : 'text-neutral-900 font-medium',
              ]"
            >
              {{ n.mensaje }}
            </p>
            <span class="text-xs text-neutral-400 shrink-0">
              {{ tiempoRelativo(n.fecha_envio) }}
            </span>
          </div>
          <p class="text-xs text-neutral-500 mt-1">{{ etiquetaTipo(n.tipo) }}</p>
        </div>
        <span
          v-if="!n.read_at"
          class="w-2 h-2 mt-2 rounded-full bg-primary-500 shrink-0"
        ></span>
      </li>
    </ul>

    <nav
      v-if="meta.last_page > 1"
      class="flex items-center justify-center gap-2 mt-6"
    >
      <AppButton
        variant="outline"
        size="sm"
        :disabled="pagina <= 1"
        @click="irPagina(pagina - 1)"
      >
        Anterior
      </AppButton>
      <span class="text-sm text-neutral-600 px-2">
        Página {{ meta.current_page }} de {{ meta.last_page }}
      </span>
      <AppButton
        variant="outline"
        size="sm"
        :disabled="pagina >= meta.last_page"
        @click="irPagina(pagina + 1)"
      >
        Siguiente
      </AppButton>
    </nav>
  </div>
</template>
