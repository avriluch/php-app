<script setup>
import { ref, onMounted } from 'vue'
import { Calendar } from '@lucide/vue'
import api from '@/services/api'
import AppCard from '@/components/ui/AppCard.vue'
import AppSpinner from '@/components/ui/AppSpinner.vue'
import AppButton from '@/components/ui/AppButton.vue'
import { RouterLink } from 'vue-router'

const loading = ref(true)
const error = ref(null)
const bookings = ref([])

onMounted(async () => {
  loading.value = true
  error.value = null
  try {
    const { data } = await api.get('/bookings')
    bookings.value = data.data ?? data ?? []
  } catch (e) {
    if (e.response?.status === 501) {
      error.value = 'pendiente'
    } else {
      error.value = e.response?.data?.message ?? 'No se pudieron cargar las reservas.'
    }
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div>
    <h1 class="text-2xl font-bold text-neutral-900 mb-2">Mis reservas</h1>
    <p class="text-neutral-500 mb-8">Turnos que reservaste con profesionales.</p>

    <div v-if="loading" class="flex justify-center py-16">
      <AppSpinner size="lg" />
    </div>

    <AppCard v-else-if="error === 'pendiente'" class="text-center py-12">
      <Calendar class="w-10 h-10 text-neutral-400 mx-auto mb-3" />
      <p class="font-medium text-neutral-800 mb-2">API en desarrollo</p>
      <p class="text-sm text-neutral-500 mb-6">
        El endpoint <code class="text-xs bg-neutral-100 px-1 rounded">GET /api/bookings</code> debe
        implementarse en el backend.
      </p>
      <AppButton variant="primary" as="RouterLink" to="/professionals">Buscar profesionales</AppButton>
    </AppCard>

    <p v-else-if="error" class="text-red-600 text-sm">{{ error }}</p>

    <AppCard v-else-if="bookings.length === 0" class="text-center py-12 text-neutral-500 text-sm">
      No tenés reservas todavía.
      <AppButton variant="primary" class="mt-4" as="RouterLink" to="/professionals">
        Reservar un turno
      </AppButton>
    </AppCard>

    <div v-else class="space-y-3">
      <AppCard v-for="b in bookings" :key="b.id" padding="md">
        <pre class="text-xs text-neutral-600 overflow-auto">{{ b }}</pre>
      </AppCard>
    </div>
  </div>
</template>
