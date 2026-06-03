<script setup>
import { ref, onMounted, computed } from 'vue'
import { Star } from '@lucide/vue'
import api from '@/services/api'
import AppCard from '@/components/ui/AppCard.vue'
import AppSpinner from '@/components/ui/AppSpinner.vue'
import AppAvatar from '@/components/ui/AppAvatar.vue'
import StarRating from '@/components/ui/StarRating.vue'

const loading = ref(true)
const error = ref(null)
const reviews = ref([])
const meta = ref(null)
const loadingMore = ref(false)

const avgRating = computed(() => {
  if (!meta.value?.total) return null
  const sum = reviews.value.reduce((acc, r) => acc + r.puntaje, 0)
  return reviews.value.length ? (sum / reviews.value.length).toFixed(1) : null
})

const formatFecha = (iso) => new Date(iso).toLocaleDateString('es-UY', {
  day: 'numeric', month: 'long', year: 'numeric',
})

function reviewerName(r) {
  return [r.cliente?.nombre, r.cliente?.apellido].filter(Boolean).join(' ') || 'Cliente'
}

async function load(page = 1) {
  if (page === 1) loading.value = true
  else loadingMore.value = true
  error.value = null
  try {
    const { data } = await api.get('/professional/reviews', { params: { page, per_page: 10 } })
    if (page === 1) reviews.value = data.data ?? []
    else reviews.value = [...reviews.value, ...(data.data ?? [])]
    meta.value = data.meta ?? null
  } catch (e) {
    error.value = e.response?.data?.message ?? 'No se pudieron cargar las reseñas.'
  } finally {
    loading.value = false
    loadingMore.value = false
  }
}

onMounted(() => load())
</script>

<template>
  <div>
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-neutral-900 mb-1">Reseñas</h1>
      <p class="text-neutral-500 text-sm">Lo que dicen tus clientes.</p>
    </div>

    <div v-if="loading" class="flex justify-center py-16">
      <AppSpinner size="lg" />
    </div>

    <p v-else-if="error" class="text-red-600 text-sm">{{ error }}</p>

    <template v-else>
      <!-- Resumen -->
      <AppCard v-if="reviews.length > 0" padding="md" class="mb-6">
        <div class="flex items-center gap-4">
          <div class="text-center">
            <p class="text-4xl font-bold text-neutral-900">{{ avgRating }}</p>
            <StarRating :model-value="parseFloat(avgRating)" readonly size="sm" class="mt-1" />
          </div>
          <div class="text-sm text-neutral-500">
            <p><span class="font-semibold text-neutral-900">{{ meta?.total }}</span> reseñas en total</p>
          </div>
        </div>
      </AppCard>

      <AppCard v-if="reviews.length === 0" class="text-center py-16">
        <Star class="w-12 h-12 text-neutral-300 mx-auto mb-3" />
        <p class="font-medium text-neutral-700 mb-1">Todavía no tenés reseñas</p>
        <p class="text-sm text-neutral-500">Aparecerán aquí cuando los clientes califiquen sus sesiones.</p>
      </AppCard>

      <div v-else class="space-y-4">
        <AppCard v-for="r in reviews" :key="r.id" padding="md">
          <div class="flex items-start gap-3">
            <AppAvatar :name="reviewerName(r)" :src="r.cliente?.foto_perfil" size="sm" />
            <div class="flex-1 min-w-0">
              <div class="flex items-center justify-between gap-2 flex-wrap">
                <span class="text-sm font-medium text-neutral-900">{{ reviewerName(r) }}</span>
                <span class="text-xs text-neutral-400">{{ formatFecha(r.fecha) }}</span>
              </div>
              <StarRating :model-value="r.puntaje" readonly size="sm" class="mt-1" />
              <p v-if="r.comentario" class="mt-2 text-sm text-neutral-600 leading-relaxed">
                {{ r.comentario }}
              </p>
            </div>
          </div>
        </AppCard>

        <div v-if="meta && meta.current_page < meta.last_page" class="text-center">
          <button
            class="text-sm text-primary-600 hover:underline cursor-pointer"
            :disabled="loadingMore"
            @click="load(meta.current_page + 1)"
          >
            {{ loadingMore ? 'Cargando...' : 'Ver más' }}
          </button>
        </div>
      </div>
    </template>
  </div>
</template>
