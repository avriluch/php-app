<script setup>
import { ref, onMounted } from 'vue'
import { Star } from '@lucide/vue'
import api from '@/services/api'
import AppCard from '@/components/ui/AppCard.vue'
import AppSpinner from '@/components/ui/AppSpinner.vue'
import StarRating from '@/components/ui/StarRating.vue'

const loading = ref(true)
const error = ref(null)
const reviews = ref([])

const formatFecha = (iso) => new Date(iso).toLocaleDateString('es-UY', {
  day: 'numeric', month: 'long', year: 'numeric',
})

onMounted(async () => {
  try {
    const { data } = await api.get('/reviews/mine')
    reviews.value = data.data ?? []
  } catch (e) {
    error.value = e.response?.data?.message ?? 'No se pudieron cargar las reseñas.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div>
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-neutral-900 mb-1">Mis reseñas</h1>
      <p class="text-neutral-500 text-sm">Calificaciones que escribiste a los profesionales.</p>
    </div>

    <div v-if="loading" class="flex justify-center py-16">
      <AppSpinner size="lg" />
    </div>

    <p v-else-if="error" class="text-red-600 text-sm">{{ error }}</p>

    <AppCard v-else-if="reviews.length === 0" class="text-center py-16">
      <Star class="w-12 h-12 text-neutral-300 mx-auto mb-3" />
      <p class="font-medium text-neutral-700 mb-1">Todavía no escribiste reseñas</p>
      <p class="text-sm text-neutral-500">Las reseñas aparecen aquí luego de que finalizás una sesión.</p>
    </AppCard>

    <div v-else class="space-y-4">
      <AppCard v-for="r in reviews" :key="r.id" padding="md">
        <div class="flex items-start justify-between gap-4 flex-wrap">
          <div>
            <p class="font-semibold text-neutral-900">
              {{ r.profesional?.nombre }} {{ r.profesional?.apellido }}
            </p>
            <StarRating :model-value="r.puntaje" readonly size="sm" class="mt-1" />
            <p v-if="r.comentario" class="mt-2 text-sm text-neutral-600 italic leading-relaxed">
              "{{ r.comentario }}"
            </p>
          </div>
          <span class="text-xs text-neutral-400 shrink-0">{{ formatFecha(r.fecha) }}</span>
        </div>
      </AppCard>
    </div>
  </div>
</template>
