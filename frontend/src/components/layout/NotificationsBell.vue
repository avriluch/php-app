<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { RouterLink } from 'vue-router'
import { Bell, Check, CheckCheck, Calendar, AlertCircle } from '@lucide/vue'
import { useNotificationsStore } from '@/stores/notifications'

const store = useNotificationsStore()
const abierto = ref(false)
const wrapper = ref(null)

const badge = computed(() => {
  if (!store.unreadCount) return ''
  return store.unreadCount > 99 ? '99+' : String(store.unreadCount)
})

function alternar() {
  abierto.value = !abierto.value
}

function cerrar() {
  abierto.value = false
}

function manejarClickFuera(evento) {
  if (wrapper.value && !wrapper.value.contains(evento.target)) {
    cerrar()
  }
}

async function clickItem(item) {
  await store.marcarLeida(item.id)
  cerrar()
}

function tiempoRelativo(iso) {
  if (!iso) return ''
  const fecha = new Date(iso)
  const segundos = Math.floor((Date.now() - fecha.getTime()) / 1000)
  if (segundos < 60) return 'recién'
  const minutos = Math.floor(segundos / 60)
  if (minutos < 60) return `hace ${minutos} min`
  const horas = Math.floor(minutos / 60)
  if (horas < 24) return `hace ${horas} h`
  const dias = Math.floor(horas / 24)
  if (dias < 7) return `hace ${dias} d`
  return fecha.toLocaleDateString('es-UY')
}

function iconoPorTipo(tipo) {
  if (tipo === 'cancelacion') return AlertCircle
  if (tipo === 'recordatorio') return Calendar
  return Check
}

function colorPorTipo(tipo) {
  if (tipo === 'cancelacion') return 'text-red-500'
  if (tipo === 'recordatorio') return 'text-amber-500'
  return 'text-green-500'
}

// El init() del store es idempotente; lo llamamos cada vez que el bell se monta.
// La limpieza vive en auth.logout() para que sobreviva entre layouts.
onMounted(async () => {
  document.addEventListener('mousedown', manejarClickFuera)
  await store.init()
})

onBeforeUnmount(() => {
  document.removeEventListener('mousedown', manejarClickFuera)
})
</script>

<template>
  <div ref="wrapper" class="relative">
    <button
      type="button"
      class="relative p-2 rounded-lg text-neutral-600 hover:text-primary-600 hover:bg-neutral-100 transition-colors cursor-pointer"
      :title="store.unreadCount ? `${store.unreadCount} sin leer` : 'Notificaciones'"
      @click="alternar"
    >
      <Bell class="w-5 h-5" />
      <span
        v-if="badge"
        class="absolute top-1 right-1 min-w-[18px] h-[18px] px-1 rounded-full bg-red-600 text-white text-[10px] font-bold flex items-center justify-center"
      >
        {{ badge }}
      </span>
    </button>

    <div
      v-if="abierto"
      class="absolute right-0 mt-2 w-80 bg-white rounded-xl border border-neutral-200 z-50 overflow-hidden"
      style="box-shadow: var(--shadow-modal)"
    >
      <div
        class="flex items-center justify-between px-4 py-3 border-b border-neutral-100"
      >
        <h3 class="text-sm font-semibold text-neutral-900">Notificaciones</h3>
        <button
          v-if="store.unreadCount > 0"
          type="button"
          class="text-xs text-primary-600 hover:text-primary-700 flex items-center gap-1 cursor-pointer"
          @click="store.marcarTodasLeidas()"
        >
          <CheckCheck class="w-3.5 h-3.5" /> Marcar todas
        </button>
      </div>

      <div class="max-h-80 overflow-y-auto">
        <div
          v-if="store.cargando && !store.items.length"
          class="px-4 py-8 text-center text-sm text-neutral-400"
        >
          Cargando…
        </div>
        <div
          v-else-if="!store.ultimas.length"
          class="px-4 py-8 text-center text-sm text-neutral-400"
        >
          No tenés notificaciones.
        </div>
        <ul v-else class="divide-y divide-neutral-100">
          <li
            v-for="n in store.ultimas"
            :key="n.id"
            :class="[
              'px-4 py-3 cursor-pointer hover:bg-neutral-50 transition-colors',
              !n.read_at && 'bg-primary-50/40',
            ]"
            @click="clickItem(n)"
          >
            <div class="flex items-start gap-3">
              <component
                :is="iconoPorTipo(n.tipo)"
                :class="['w-4 h-4 mt-0.5 shrink-0', colorPorTipo(n.tipo)]"
              />
              <div class="flex-1 min-w-0">
                <p
                  :class="[
                    'text-sm leading-snug',
                    n.read_at ? 'text-neutral-600' : 'text-neutral-900 font-medium',
                  ]"
                >
                  {{ n.mensaje }}
                </p>
                <p class="text-xs text-neutral-400 mt-1">
                  {{ tiempoRelativo(n.fecha_envio) }}
                </p>
              </div>
              <span
                v-if="!n.read_at"
                class="w-2 h-2 mt-2 rounded-full bg-primary-500 shrink-0"
              ></span>
            </div>
          </li>
        </ul>
      </div>

      <RouterLink
        to="/dashboard/notifications"
        class="block text-center px-4 py-2.5 text-sm font-medium text-primary-600 hover:bg-neutral-50 border-t border-neutral-100 no-underline"
        @click="cerrar"
      >
        Ver todas
      </RouterLink>
    </div>
  </div>
</template>
