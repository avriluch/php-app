<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import { Star, MapPin, Video, Clock, Calendar } from '@lucide/vue'
import api from '@/services/api'
import AppButton from '@/components/ui/AppButton.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppAvatar from '@/components/ui/AppAvatar.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppSpinner from '@/components/ui/AppSpinner.vue'

const route = useRoute()
const loading = ref(true)
const error = ref(null)
const professional = ref(null)

const fullName = computed(() => {
  if (!professional.value) return ''
  return `${professional.value.nombre} ${professional.value.apellido}`.trim()
})

async function loadProfessional() {
  loading.value = true
  error.value = null
  try {
    const { data } = await api.get(`/professionals/${route.params.id}`)
    professional.value = data
  } catch (e) {
    error.value =
      e.response?.status === 404
        ? 'Profesional no encontrado.'
        : (e.response?.data?.message ?? 'Error al cargar el perfil.')
  } finally {
    loading.value = false
  }
}

function modalidadLabel(m) {
  const labels = { virtual: 'Virtual', presencial: 'Presencial', hibrida: 'Híbrida' }
  return labels[m] ?? m
}

function formatPrice(price) {
  return new Intl.NumberFormat('es-UY', { style: 'currency', currency: 'UYU' }).format(price)
}

onMounted(loadProfessional)
</script>

<template>
  <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div v-if="loading" class="flex justify-center py-20">
      <AppSpinner size="lg" />
    </div>

    <AppCard v-else-if="error" class="text-center py-16">
      <p class="text-red-600 mb-4">{{ error }}</p>
      <AppButton variant="outline" as="RouterLink" to="/professionals">Volver al listado</AppButton>
    </AppCard>

    <template v-else-if="professional">
      <div class="flex flex-col sm:flex-row gap-6 mb-8">
        <AppAvatar :name="fullName" :src="professional.foto_perfil" size="xl" />
        <div class="flex-1">
          <h1 class="text-3xl font-bold text-neutral-900">{{ fullName }}</h1>
          <p class="text-lg text-primary-600 font-medium mt-1">{{ professional.titulo }}</p>
          <div v-if="professional.rating_count > 0" class="flex items-center gap-1 mt-2 text-neutral-600">
            <Star class="w-5 h-5 text-amber-400 fill-amber-400" />
            <span class="font-medium">{{ professional.rating_avg }}</span>
            <span class="text-neutral-400">({{ professional.rating_count }} reseñas)</span>
          </div>
          <p
            v-if="professional.ubicacion"
            class="flex items-center gap-1 mt-2 text-sm text-neutral-500"
          >
            <MapPin class="w-4 h-4" />
            {{ professional.ubicacion.ciudad }}, {{ professional.ubicacion.pais }}
          </p>
          <div class="flex flex-wrap gap-2 mt-4">
            <AppBadge v-for="m in professional.modalidades" :key="m" variant="primary">
              {{ modalidadLabel(m) }}
            </AppBadge>
          </div>
        </div>
        <div class="shrink-0">
          <AppButton variant="primary" as="RouterLink" :to="`/book/${professional.id}`">
            <Calendar class="w-4 h-4" /> Reservar turno
          </AppButton>
        </div>
      </div>

      <p v-if="professional.descripcion" class="text-neutral-600 mb-8 leading-relaxed">
        {{ professional.descripcion }}
      </p>

      <AppCard v-if="professional.agenda_resumen" class="mb-8" padding="md">
        <h2 class="font-semibold text-neutral-900 mb-3 flex items-center gap-2">
          <Clock class="w-5 h-5 text-neutral-500" /> Disponibilidad habitual
        </h2>
        <p class="text-sm text-neutral-600">
          {{ professional.agenda_resumen.horario_inicio }} – {{ professional.agenda_resumen.horario_fin }}
          · buffer {{ professional.agenda_resumen.buffer_minutos }} min
        </p>
      </AppCard>

      <h2 class="text-xl font-semibold text-neutral-900 mb-4">Servicios</h2>
      <div v-if="!professional.servicios?.length" class="text-neutral-500 text-sm mb-8">
        Sin servicios publicados.
      </div>
      <div v-else class="grid gap-4 sm:grid-cols-2 mb-8">
        <AppCard v-for="svc in professional.servicios" :key="svc.id" padding="md">
          <div class="flex justify-between items-start gap-2">
            <div>
              <h3 class="font-semibold text-neutral-900">{{ svc.nombre }}</h3>
              <p v-if="svc.descripcion" class="text-sm text-neutral-500 mt-1 line-clamp-2">
                {{ svc.descripcion }}
              </p>
            </div>
            <span class="font-bold text-primary-600 shrink-0">{{ formatPrice(svc.precio) }}</span>
          </div>
          <div class="flex flex-wrap gap-2 mt-3 text-sm text-neutral-600">
            <AppBadge variant="default" size="sm">{{ modalidadLabel(svc.modalidad) }}</AppBadge>
            <span v-if="svc.duracion">{{ svc.duracion }} min</span>
            <span v-if="svc.type === 'package'">· {{ svc.cantidad_sesiones }} sesiones</span>
            <Video v-if="svc.modalidad === 'virtual'" class="w-4 h-4 text-primary-500" />
          </div>
        </AppCard>
      </div>
    </template>
  </div>
</template>
