<script setup>
import { ref, watch, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { Search, SlidersHorizontal, Star, MapPin } from '@lucide/vue'
import api from '@/services/api'
import AppButton from '@/components/ui/AppButton.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppAvatar from '@/components/ui/AppAvatar.vue'
import AppSpinner from '@/components/ui/AppSpinner.vue'

const loading = ref(false)
const error = ref(null)
const search = ref('')
const professionals = ref([])
const meta = ref(null)

let searchTimeout = null

async function fetchProfessionals() {
  loading.value = true
  error.value = null
  try {
    const { data } = await api.get('/professionals', {
      params: { search: search.value.trim() || undefined },
    })
    professionals.value = data.data ?? []
    meta.value = data.meta ?? null
  } catch (e) {
    error.value = e.response?.data?.message ?? 'No se pudo cargar el listado.'
    professionals.value = []
  } finally {
    loading.value = false
  }
}

watch(search, () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(fetchProfessionals, 350)
})

onMounted(fetchProfessionals)

function fullName(p) {
  return `${p.nombre} ${p.apellido}`.trim()
}

function modalidadLabel(m) {
  const labels = { virtual: 'Virtual', presencial: 'Presencial', hibrida: 'Híbrida' }
  return labels[m] ?? m
}
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="mb-8">
      <h1 class="text-3xl font-bold text-neutral-900 mb-2">Profesionales</h1>
      <p class="text-neutral-500">Encontrá al profesional ideal para tus necesidades</p>
    </div>

    <div class="flex flex-col sm:flex-row gap-3 mb-8">
      <div class="flex-1 relative">
        <Search class="w-4 h-4 text-neutral-400 absolute left-3 top-1/2 -translate-y-1/2" />
        <input
          v-model="search"
          type="text"
          placeholder="Buscar por nombre, especialidad..."
          class="w-full pl-9 pr-4 py-2 text-sm border border-neutral-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
        />
      </div>
      <AppButton variant="outline" size="md" disabled>
        <SlidersHorizontal class="w-4 h-4" /> Filtros
      </AppButton>
    </div>

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
      <p class="text-sm text-neutral-500">Probá otro término de búsqueda.</p>
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
      {{ meta.total }} profesional{{ meta.total !== 1 ? 'es' : '' }}
    </p>
  </div>
</template>
