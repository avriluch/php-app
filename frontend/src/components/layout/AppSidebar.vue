<script setup>
import { computed } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import {
  LayoutDashboard, Calendar, Users, Settings, Star, CreditCard,
  Briefcase, Bell, X,
} from '@lucide/vue'
import AppAvatar from '@/components/ui/AppAvatar.vue'
import { useAuthStore } from '@/stores/auth'
import { useUIStore } from '@/stores/ui'

const auth = useAuthStore()
const ui = useUIStore()
const route = useRoute()

const navByRole = {
  client: [
    { label: 'Panel', to: '/dashboard/client', icon: LayoutDashboard },
    { label: 'Mis reservas', to: '/dashboard/client/bookings', icon: Calendar },
    { label: 'Mis paquetes', to: '/dashboard/client/packages', icon: Briefcase },
    { label: 'Mis reseñas', to: '/dashboard/client/reviews', icon: Star },
    { label: 'Pagos', to: '/dashboard/client/payments', icon: CreditCard },
    { label: 'Notificaciones', to: '/dashboard/notifications', icon: Bell },
  ],
  professional: [
    { label: 'Panel', to: '/dashboard/professional', icon: LayoutDashboard },
    { label: 'Reservas', to: '/dashboard/professional/bookings', icon: Calendar },
    { label: 'Agenda', to: '/dashboard/professional/schedule', icon: Calendar },
    { label: 'Clientes', to: '/dashboard/professional/clients', icon: Users },
    { label: 'Servicios', to: '/dashboard/professional/services', icon: Briefcase },
    { label: 'Paquetes', to: '/dashboard/professional/packages', icon: Briefcase },
    { label: 'Reseñas', to: '/dashboard/professional/reviews', icon: Star },
    { label: 'Notificaciones', to: '/dashboard/notifications', icon: Bell },
    { label: 'Configuración', to: '/dashboard/professional/settings', icon: Settings },
  ],
  admin: [
    { label: 'Panel', to: '/admin', icon: LayoutDashboard },
    { label: 'Usuarios', to: '/admin/users', icon: Users },
    { label: 'Configuración', to: '/admin/settings', icon: Settings },
  ],
}

const links = computed(() => navByRole[auth.role] ?? [])

// Un link está activo solo si es la coincidencia MÁS específica para la ruta actual.
// Evita que "Panel" (cuyo path es prefijo de todas las sub-rutas) quede siempre marcado.
const isActive = (to) => {
  const coincidencias = links.value
    .map((l) => l.to)
    .filter((t) => route.path === t || route.path.startsWith(t + '/'))
  if (coincidencias.length === 0) return false
  const masEspecifico = coincidencias.reduce((a, b) => (b.length > a.length ? b : a))
  return to === masEspecifico
}
</script>

<template>
  <aside
    :class="[
      'flex flex-col h-full bg-white border-r border-neutral-200 transition-all duration-300',
      ui.sidebarOpen ? 'w-64' : 'w-0 md:w-16 overflow-hidden',
    ]"
  >
    <!-- Header -->
    <div class="flex items-center justify-between px-4 h-16 border-b border-neutral-100 shrink-0">
      <RouterLink to="/" class="flex items-center gap-2 no-underline overflow-hidden">
        <span class="w-7 h-7 bg-primary-600 rounded-lg flex items-center justify-center shrink-0">
          <span class="text-white font-bold text-xs">S</span>
        </span>
        <span v-if="ui.sidebarOpen" class="font-bold text-neutral-900 whitespace-nowrap">ServiConnect</span>
      </RouterLink>
      <button
        class="md:hidden p-1 rounded-lg hover:bg-neutral-100 cursor-pointer text-neutral-500"
        @click="ui.sidebarOpen = false"
      >
        <X class="w-4 h-4" />
      </button>
    </div>

    <!-- Nav -->
    <nav class="flex-1 overflow-y-auto py-4 px-2">
      <RouterLink
        v-for="link in links"
        :key="link.to"
        :to="link.to"
        :class="[
          'flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors no-underline mb-0.5',
          isActive(link.to)
            ? 'bg-primary-50 text-primary-700'
            : 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900',
        ]"
        :title="!ui.sidebarOpen ? link.label : undefined"
      >
        <component :is="link.icon" class="w-5 h-5 shrink-0" />
        <span v-if="ui.sidebarOpen" class="whitespace-nowrap">{{ link.label }}</span>
      </RouterLink>
    </nav>

    <!-- User footer -->
    <div class="px-3 py-4 border-t border-neutral-100 shrink-0">
      <div class="flex items-center gap-3">
        <AppAvatar :name="auth.displayName" size="sm" />
        <div v-if="ui.sidebarOpen" class="overflow-hidden">
          <p class="text-sm font-medium text-neutral-900 truncate">{{ auth.displayName }}</p>
          <p class="text-xs text-neutral-500 truncate">{{ auth.user?.email }}</p>
        </div>
      </div>
    </div>
  </aside>
</template>
