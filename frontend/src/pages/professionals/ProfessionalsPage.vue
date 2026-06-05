<script setup>
import { ref, reactive, watch, onMounted, computed } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { Search, SlidersHorizontal, Star, MapPin, X, ChevronDown } from '@lucide/vue'
import api from '@/services/api'
import AppButton from '@/components/ui/AppButton.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppAvatar from '@/components/ui/AppAvatar.vue'
import AppSpinner from '@/components/ui/AppSpinner.vue'

const loading = ref(false)
const error = ref(null)
const professionals = ref([])
const meta = ref(null)
const filtersOpen = ref(false)

const route = useRoute()
const search = ref('')

const filters = reactive({
  modalidad: '',
  type: '',
  precio_min: '',
  precio_max: '',
  ciudad: '',
  rating_min: '',
  sort: 'rating',
})

const activeFilterCount = computed(() =>
  Object.entries(filters).filter(([k, v]) => v && !(k === 'sort' && v === 'rating')).length
)

function buildParams() {
  const p = {}
  if (search.value.trim()) p.search = search.value.trim()
  if (filters.modalidad) p.modalidad = filters.modalidad
  if (filters.type) p.type = filters.type
  if (filters.precio_min !== '') p.precio_min = filters.precio_min
  if (filters.precio_max !== '') p.precio_max = filters.precio_max
  if (filters.ciudad.trim()) p.ciudad = filters.ciudad.trim()
  if (filters.rating_min) p.rating_min = filters.rating_min
  if (filters.sort) p.sort = filters.sort
  return p
}

async function fetchProfessionals() {
  loading.value = true
  error.value = null
  try {
    const { data } = await api.get('/professionals', { params: buildParams() })
    professionals.value = data.data ?? []
    meta.value = data.meta ?? null
  } catch (e) {
    error.value = e.response?.data?.message ?? 'No se pudo cargar el listado.'
    professionals.value = []
  } finally {
    loading.value = false
  }
}

function clearFilters() {
  filters.modalidad = ''
  filters.type = ''
  filters.precio_min = ''
  filters.precio_max = ''
  filters.ciudad = ''
  filters.rating_min = ''
  filters.sort = 'rating'
}

let debounceTimer = null
function debouncedFetch() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(fetchProfessionals, 350)
}

watch(search, debouncedFetch)
watch(filters, fetchProfessionals)

onMounted(() => {
  if (route.query.type === 'package' || route.query.type === 'session') {
    filters.type = route.query.type
  }
  const q = route.query.search ?? route.query.q
  if (typeof q === 'string' && q.trim()) {
    search.value = q.trim()
  }
  fetchProfessionals()
})

function fullName(p) { return `${p.nombre} ${p.apellido}`.trim() }
function modalidadLabel(m) {
  return { virtual: 'Virtual', presencial: 'Presencial', hibrida: 'Híbrida' }[m] ?? m
}
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">

    <div class="mb-8">
      <h1 class="text-3xl font-bold text-neutral-900 mb-2">Profesionales</h1>
      <p class="text-neutral-500">Encontrá al profesional ideal para tus necesidades</p>
    </div>

    <!-- Barra de búsqueda + botón filtros -->
    <div class="flex flex-col sm:flex-row gap-3 mb-4">
      <div class="flex-1 relative">
        <Search class="w-4 h-4 text-neutral-400 absolute left-3 top-1/2 -translate-y-1/2" />
        <input
          v-model="search"
          type="text"
          placeholder="Buscar por nombre, especialidad..."
          class="w-full pl-9 pr-4 py-2 text-sm border border-neutral-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
        />
      </div>
      <button
        class="flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg border transition-colors cursor-pointer"
        :class="filtersOpen || activeFilterCount > 0
          ? 'border-primary-500 bg-primary-50 text-primary-700'
          : 'border-neutral-300 bg-white text-neutral-700 hover:bg-neutral-50'"
        @click="filtersOpen = !filtersOpen"
      >
        <SlidersHorizontal class="w-4 h-4" />
        Filtros
        <span
          v-if="activeFilterCount > 0"
          class="w-5 h-5 rounded-full bg-primary-600 text-white text-xs flex items-center justify-center font-bold"
        >
          {{ activeFilterCount }}
        </span>
        <ChevronDown class="w-4 h-4 transition-transform" :class="filtersOpen ? 'rotate-180' : ''" />
      </button>
    </div>

    <!-- Panel de filtros -->
    <div v-if="filtersOpen" class="mb-6 p-4 bg-white border border-neutral-200 rounded-xl shadow-sm">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

        <!-- Modalidad -->
        <div>
          <label class="block text-xs font-semibold text-neutral-600 mb-1.5 uppercase tracking-wide">Modalidad</label>
          <div class="flex flex-wrap gap-2">
            <button
              v-for="opt in [{ value: '', label: 'Todas' }, { value: 'virtual', label: 'Virtual' }, { value: 'presencial', label: 'Presencial' }, { value: 'hibrida', label: 'Híbrida' }]"
              :key="opt.value"
              class="px-3 py-1 text-sm rounded-full border transition-colors cursor-pointer"
              :class="filters.modalidad === opt.value
                ? 'bg-primary-600 border-primary-600 text-white'
                : 'border-neutral-300 text-neutral-600 hover:border-primary-400'"
              @click="filters.modalidad = opt.value"
            >
              {{ opt.label }}
            </button>
          </div>
        </div>

        <!-- Tipo de servicio -->
        <div>
          <label class="block text-xs font-semibold text-neutral-600 mb-1.5 uppercase tracking-wide">Tipo de servicio</label>
          <div class="flex flex-wrap gap-2">
            <button
              v-for="opt in [{ value: '', label: 'Todos' }, { value: 'session', label: 'Sesión individual' }, { value: 'package', label: 'Paquete' }]"
              :key="opt.value"
              class="px-3 py-1 text-sm rounded-full border transition-colors cursor-pointer"
              :class="filters.type === opt.value
                ? 'bg-primary-600 border-primary-600 text-white'
                : 'border-neutral-300 text-neutral-600 hover:border-primary-400'"
              @click="filters.type = opt.value"
            >
              {{ opt.label }}
            </button>
          </div>
        </div>

        <!-- Calificación mínima -->
        <div>
          <label class="block text-xs font-semibold text-neutral-600 mb-1.5 uppercase tracking-wide">Calificación mínima</label>
          <div class="flex gap-1">
            <button
              v-for="n in [1, 2, 3, 4, 5]"
              :key="n"
              class="cursor-pointer transition-transform hover:scale-110"
              :title="`${n} estrella${n > 1 ? 's' : ''}`"
              @click="filters.rating_min = filters.rating_min == n ? '' : n"
            >
              <Star
                class="w-6 h-6 transition-colors"
                :class="n <= (filters.rating_min || 0)
                  ? 'text-amber-400 fill-amber-400'
                  : 'text-neutral-300 fill-neutral-100'"
              />
            </button>
            <button
              v-if="filters.rating_min"
              class="ml-1 text-xs text-neutral-400 hover:text-neutral-600 cursor-pointer"
              @click="filters.rating_min = ''"
            >
              <X class="w-4 h-4" />
            </button>
          </div>
        </div>

        <!-- Rango de precio -->
        <div>
          <label class="block text-xs font-semibold text-neutral-600 mb-1.5 uppercase tracking-wide">Precio (UYU)</label>
          <div class="flex items-center gap-2">
            <input
              v-model="filters.precio_min"
              type="number"
              min="0"
              placeholder="Mín"
              class="w-full px-3 py-1.5 text-sm border border-neutral-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500"
            />
            <span class="text-neutral-400 text-sm shrink-0">—</span>
            <input
              v-model="filters.precio_max"
              type="number"
              min="0"
              placeholder="Máx"
              class="w-full px-3 py-1.5 text-sm border border-neutral-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500"
            />
          </div>
        </div>

        <!-- Ciudad -->
        <div>
          <label class="block text-xs font-semibold text-neutral-600 mb-1.5 uppercase tracking-wide">Ciudad</label>
          <div class="relative">
            <MapPin class="w-4 h-4 text-neutral-400 absolute left-3 top-1/2 -translate-y-1/2" />
            <input
              v-model="filters.ciudad"
              type="text"
              placeholder="Ej: Montevideo"
              class="w-full pl-9 pr-3 py-1.5 text-sm border border-neutral-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500"
            />
          </div>
        </div>

        <!-- Ordenar -->
        <div>
          <label class="block text-xs font-semibold text-neutral-600 mb-1.5 uppercase tracking-wide">Ordenar por</label>
          <div class="flex gap-2">
            <button
              v-for="opt in [{ value: 'rating', label: '⭐ Calificación' }, { value: 'price', label: '💲 Precio' }]"
              :key="opt.value"
              class="px-3 py-1 text-sm rounded-full border transition-colors cursor-pointer"
              :class="filters.sort === opt.value
                ? 'bg-primary-600 border-primary-600 text-white'
                : 'border-neutral-300 text-neutral-600 hover:border-primary-400'"
              @click="filters.sort = opt.value"
            >
              {{ opt.label }}
            </button>
          </div>
        </div>
      </div>

      <!-- Limpiar filtros -->
      <div class="mt-4 flex justify-end">
        <button
          v-if="activeFilterCount > 0"
          class="text-sm text-neutral-500 hover:text-neutral-700 flex items-center gap-1 cursor-pointer"
          @click="clearFilters"
        >
          <X class="w-4 h-4" /> Limpiar filtros
        </button>
      </div>
    </div>

    <!-- Resultados -->
    <div v-if="loading" class="flex justify-center py-20">
      <AppSpinner size="lg" />
    </div>

    <div v-else-if="error" class="text-center py-16 text-red-600">
      <p>{{ error }}</p>
      <AppButton variant="outline" class="mt-4" @click="fetchProfessionals">Reintentar</AppButton>
    </div>

    <div
      v-else-if="professionals.length === 0"
      class="flex flex-col items-center justify-center py-20 text-center"
    >
      <p class="font-medium text-neutral-700 mb-1">No hay profesionales</p>
      <p class="text-sm text-neutral-500">Probá con otros filtros o términos de búsqueda.</p>
      <button v-if="activeFilterCount > 0" class="mt-3 text-sm text-primary-600 hover:underline cursor-pointer" @click="clearFilters">
        Limpiar filtros
      </button>
    </div>

    <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <AppCard
        v-for="pro in professionals"
        :key="pro.id"
        hover
        as="RouterLink"
        :to="`/professionals/${pro.id}`"
        class="block no-underline text-inherit"
      >
        <div class="flex gap-4">
          <AppAvatar :name="fullName(pro)" :src="pro.foto_perfil" size="lg" />
          <div class="flex-1 min-w-0">
            <h2 class="font-semibold text-neutral-900 truncate">{{ fullName(pro) }}</h2>
            <p class="text-sm text-primary-600 font-medium">{{ pro.titulo }}</p>
            <div v-if="pro.rating_count > 0" class="flex items-center gap-1 mt-1 text-sm text-neutral-600">
              <Star class="w-4 h-4 text-amber-400 fill-amber-400" />
              <span>{{ pro.rating_avg }}</span>
              <span class="text-neutral-400">({{ pro.rating_count }})</span>
            </div>
            <p
              v-if="pro.ubicacion"
              class="flex items-center gap-1 mt-2 text-xs text-neutral-500 truncate"
            >
              <MapPin class="w-3.5 h-3.5 shrink-0" />
              {{ pro.ubicacion.ciudad }}, {{ pro.ubicacion.pais }}
            </p>
            <div class="flex flex-wrap gap-1 mt-3">
              <AppBadge
                v-for="m in pro.modalidades"
                :key="m"
                variant="primary"
                size="sm"
              >
                {{ modalidadLabel(m) }}
              </AppBadge>
            </div>
          </div>
        </div>
      </AppCard>
    </div>

    <p v-if="meta?.total" class="text-center text-sm text-neutral-500 mt-8">
      {{ meta.total }} profesional{{ meta.total !== 1 ? 'es' : '' }} encontrado{{ meta.total !== 1 ? 's' : '' }}
    </p>
  </div>
</template>
