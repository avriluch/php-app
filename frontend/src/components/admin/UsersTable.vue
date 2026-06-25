<script setup>
import { ref, watch, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { Search, UserPlus, Ban, RotateCcw, ChevronDown } from '@lucide/vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { useUIStore } from '@/stores/ui'
import AppCard from '@/components/ui/AppCard.vue'
import AppSpinner from '@/components/ui/AppSpinner.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppAvatar from '@/components/ui/AppAvatar.vue'

defineProps({
  showRoleFilter: { type: Boolean, default: true },
})

const auth = useAuthStore()
const ui = useUIStore()
const route = useRoute()

// Permite preseleccionar el filtro vía ?role=client|professional|admin
// (lo usa el panel admin cuando se clickea la card "Profesionales").
const ROLES_VALIDOS = ['client', 'professional', 'admin']
const roleFromQuery = typeof route.query.role === 'string' && ROLES_VALIDOS.includes(route.query.role)
  ? route.query.role
  : ''

const loading = ref(true)
const error = ref(null)
const users = ref([])
const meta = ref({ current_page: 1, last_page: 1, total: 0 })
const search = ref('')
const roleFilter = ref(roleFromQuery)
const page = ref(1)
const busyId = ref(null)

const roleLabels = { client: 'Cliente', professional: 'Profesional', admin: 'Admin' }
const roleSelectClasses = {
  client: 'border-blue-200 text-blue-700 bg-blue-50',
  professional: 'border-primary-200 text-primary-700 bg-primary-50',
  admin: 'border-red-200 text-red-700 bg-red-50',
}

const userName = (u) => [u.nombre, u.apellido].filter(Boolean).join(' ') || u.email || 'Usuario'
const isSelf = (u) => u.id === auth.user?.id

const formatFecha = (iso) =>
  iso ? new Date(iso).toLocaleDateString('es-UY', { day: 'numeric', month: 'short', year: 'numeric' }) : '—'

async function load() {
  loading.value = true
  error.value = null
  try {
    const { data } = await api.get('/admin/users', {
      params: {
        search: search.value.trim() || undefined,
        role: roleFilter.value || undefined,
        page: page.value,
      },
    })
    users.value = data.data ?? []
    meta.value = data.meta ?? { current_page: 1, last_page: 1, total: 0 }
  } catch (e) {
    error.value = e.response?.data?.message ?? 'No se pudieron cargar los usuarios.'
  } finally {
    loading.value = false
  }
}

async function changeRole(u, nuevoRol) {
  if (nuevoRol === u.role) return
  busyId.value = u.id
  try {
    await api.patch(`/admin/users/${u.id}/role`, { role: nuevoRol })
    u.role = nuevoRol
    ui.toast.success(`Rol de ${userName(u)} actualizado a ${roleLabels[nuevoRol]}.`)
  } catch (e) {
    ui.toast.error(e.response?.data?.message ?? 'No se pudo cambiar el rol.')
  } finally {
    busyId.value = null
  }
}

async function toggleStatus(u) {
  busyId.value = u.id
  const nuevo = !u.activo
  try {
    const { data } = await api.patch(`/admin/users/${u.id}/status`, { activo: nuevo })
    u.activo = data.activo
    ui.toast.success(data.message ?? 'Estado actualizado.')
  } catch (e) {
    ui.toast.error(e.response?.data?.message ?? 'No se pudo cambiar el estado.')
  } finally {
    busyId.value = null
  }
}

let searchTimer
watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    page.value = 1
    load()
  }, 350)
})
watch(roleFilter, () => {
  page.value = 1
  load()
})
watch(page, load)

// Si la URL cambia (?role=...) mientras seguimos en esta misma vista,
// alineamos el filtro. El watch de roleFilter de arriba se encarga de recargar.
watch(
  () => route.query.role,
  (nuevo) => {
    const valor = typeof nuevo === 'string' && ROLES_VALIDOS.includes(nuevo) ? nuevo : ''
    if (valor !== roleFilter.value) {
      roleFilter.value = valor
    }
  },
)

function goToPage(p) {
  if (p >= 1 && p <= meta.value.last_page) page.value = p
}

onMounted(load)
</script>

<template>
  <AppCard>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
      <p class="text-xs text-neutral-500">{{ meta.total }} en total</p>
      <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
        <div class="relative flex-1 sm:w-64">
          <Search class="w-4 h-4 text-neutral-400 absolute left-3 top-1/2 -translate-y-1/2" />
          <input
            v-model="search"
            type="search"
            placeholder="Buscar por nombre o email..."
            class="w-full pl-9 pr-3 py-2 text-sm border border-neutral-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500"
          />
        </div>
        <div v-if="showRoleFilter" class="relative">
          <select
            v-model="roleFilter"
            class="appearance-none w-full pl-3 pr-9 py-2 text-sm border border-neutral-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-primary-500 cursor-pointer"
          >
            <option value="">Todos los roles</option>
            <option value="client">Clientes</option>
            <option value="professional">Profesionales</option>
            <option value="admin">Admins</option>
          </select>
          <ChevronDown class="w-4 h-4 text-neutral-400 absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none" />
        </div>
      </div>
    </div>

    <div v-if="loading" class="flex justify-center py-12">
      <AppSpinner size="lg" />
    </div>

    <p v-else-if="error" class="text-red-600 text-sm py-6 text-center">{{ error }}</p>

    <div v-else-if="users.length === 0" class="text-center py-12">
      <UserPlus class="w-10 h-10 text-neutral-300 mx-auto mb-2" />
      <p class="text-sm text-neutral-500">No hay usuarios que coincidan con la búsqueda.</p>
    </div>

    <div v-else class="overflow-x-auto -mx-2 sm:mx-0">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-left text-xs text-neutral-400 border-b border-neutral-100">
            <th class="font-medium px-3 py-2">Usuario</th>
            <th class="font-medium px-3 py-2">Rol</th>
            <th class="font-medium px-3 py-2 hidden sm:table-cell">Estado</th>
            <th class="font-medium px-3 py-2 hidden lg:table-cell">Registro</th>
            <th class="font-medium px-3 py-2 text-right">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="u in users"
            :key="u.id"
            class="border-b border-neutral-50 hover:bg-neutral-50/60 transition-colors"
            :class="{ 'opacity-60': !u.activo }"
          >
            <td class="px-3 py-3">
              <div class="flex items-center gap-3 min-w-0">
                <AppAvatar :name="userName(u)" :src="u.foto_perfil" size="sm" class="shrink-0" />
                <div class="min-w-0">
                  <p class="font-medium text-neutral-900 truncate">
                    {{ userName(u) }}
                    <span v-if="isSelf(u)" class="text-xs font-normal text-neutral-400">(vos)</span>
                  </p>
                  <p class="text-xs text-neutral-500 truncate">{{ u.email }}</p>
                  <p v-if="u.profesional?.titulo" class="text-xs text-neutral-400 truncate">
                    {{ u.profesional.titulo }}
                  </p>
                </div>
              </div>
            </td>

            <td class="px-3 py-3">
              <div class="relative inline-block">
                <select
                  :value="u.role"
                  :disabled="isSelf(u) || busyId === u.id"
                  :class="[
                    'appearance-none text-xs font-medium border rounded-lg pl-2.5 pr-7 py-1.5 cursor-pointer focus:outline-none focus:ring-2 focus:ring-primary-500 transition-colors',
                    isSelf(u)
                      ? 'border-neutral-200 text-neutral-400 bg-neutral-50 cursor-not-allowed'
                      : roleSelectClasses[u.role] ?? 'border-neutral-200 text-neutral-700 bg-white',
                  ]"
                  @change="changeRole(u, $event.target.value)"
                >
                  <option value="client">Cliente</option>
                  <option value="professional">Profesional</option>
                  <option value="admin">Admin</option>
                </select>
                <ChevronDown
                  class="w-3.5 h-3.5 absolute right-2 top-1/2 -translate-y-1/2 pointer-events-none"
                  :class="isSelf(u) ? 'text-neutral-300' : 'opacity-60'"
                />
              </div>
            </td>

            <td class="px-3 py-3 hidden sm:table-cell">
              <AppBadge :variant="u.activo ? 'success' : 'danger'" size="xs">
                {{ u.activo ? 'Activo' : 'Suspendido' }}
              </AppBadge>
            </td>

            <td class="px-3 py-3 hidden lg:table-cell text-neutral-500 text-xs">
              {{ formatFecha(u.created_at) }}
            </td>

            <td class="px-3 py-3 text-right">
              <button
                v-if="!isSelf(u)"
                :disabled="busyId === u.id"
                class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1.5 rounded-lg border transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                :class="u.activo
                  ? 'border-red-200 text-red-600 hover:bg-red-50'
                  : 'border-emerald-200 text-emerald-600 hover:bg-emerald-50'"
                @click="toggleStatus(u)"
              >
                <component :is="u.activo ? Ban : RotateCcw" class="w-3.5 h-3.5" />
                {{ u.activo ? 'Suspender' : 'Activar' }}
              </button>
              <span v-else class="text-xs text-neutral-300">—</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div
      v-if="!loading && !error && meta.last_page > 1"
      class="flex items-center justify-between mt-4 pt-4 border-t border-neutral-100"
    >
      <button
        class="text-sm px-3 py-1.5 rounded-lg border border-neutral-300 text-neutral-600 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-neutral-50"
        :disabled="meta.current_page <= 1"
        @click="goToPage(meta.current_page - 1)"
      >
        Anterior
      </button>
      <span class="text-xs text-neutral-500">Página {{ meta.current_page }} de {{ meta.last_page }}</span>
      <button
        class="text-sm px-3 py-1.5 rounded-lg border border-neutral-300 text-neutral-600 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-neutral-50"
        :disabled="meta.current_page >= meta.last_page"
        @click="goToPage(meta.current_page + 1)"
      >
        Siguiente
      </button>
    </div>
  </AppCard>
</template>
