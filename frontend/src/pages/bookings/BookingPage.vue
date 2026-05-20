<script setup>
import { useRoute } from 'vue-router'
import { Calendar, CreditCard } from '@lucide/vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppButton from '@/components/ui/AppButton.vue'

const route = useRoute()
// TODO: flujo de reserva — selección de servicio, horario, pago
</script>

<template>
  <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <h1 class="text-2xl font-bold text-neutral-900 mb-2">Reservar turno</h1>
    <p class="text-neutral-500 mb-8">Profesional #{{ route.params.professionalId }}</p>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
      <AppCard class="flex items-center gap-3">
        <div class="w-8 h-8 rounded-lg bg-primary-50 flex items-center justify-center">
          <span class="text-primary-600 font-bold text-sm">1</span>
        </div>
        <span class="text-sm font-medium text-neutral-700">Elegí servicio</span>
      </AppCard>
      <AppCard class="flex items-center gap-3">
        <div class="w-8 h-8 rounded-lg bg-neutral-100 flex items-center justify-center">
          <Calendar class="w-4 h-4 text-neutral-400" />
        </div>
        <span class="text-sm text-neutral-400">Seleccioná fecha</span>
      </AppCard>
      <AppCard class="flex items-center gap-3">
        <div class="w-8 h-8 rounded-lg bg-neutral-100 flex items-center justify-center">
          <CreditCard class="w-4 h-4 text-neutral-400" />
        </div>
        <span class="text-sm text-neutral-400">Pago</span>
      </AppCard>
    </div>

    <AppCard class="text-center py-12">
      <p class="text-sm text-neutral-500">El flujo de reserva se implementará aquí.</p>
    </AppCard>
  </div>
</template>
