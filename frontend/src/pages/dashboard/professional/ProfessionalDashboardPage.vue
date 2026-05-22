<script setup>
import { Calendar, Users, BarChart2, Star } from '@lucide/vue'
import AppCard from '@/components/ui/AppCard.vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()

const stats = [
  { label: 'Turnos hoy', value: '—', icon: Calendar, color: 'text-primary-600 bg-primary-50' },
  { label: 'Clientes activos', value: '—', icon: Users, color: 'text-accent-600 bg-accent-50' },
  { label: 'Ingresos del mes', value: '—', icon: BarChart2, color: 'text-purple-600 bg-purple-50' },
  { label: 'Calificación', value: '—', icon: Star, color: 'text-amber-600 bg-amber-50' },
]
</script>

<template>
  <div>
    <div class="mb-8">
      <h1 class="text-2xl font-bold text-neutral-900">Panel profesional 💼</h1>
      <p class="text-neutral-500 mt-1">Hola {{ auth.displayName }}, aquí están tus métricas del día.</p>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
      <AppCard v-for="stat in stats" :key="stat.label" padding="sm">
        <div class="flex items-center gap-3">
          <div :class="['w-9 h-9 rounded-xl flex items-center justify-center shrink-0', stat.color]">
            <component :is="stat.icon" class="w-4 h-4" />
          </div>
          <div>
            <p class="text-xl font-bold text-neutral-900">{{ stat.value }}</p>
            <p class="text-xs text-neutral-500">{{ stat.label }}</p>
          </div>
        </div>
      </AppCard>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <AppCard>
        <h2 class="font-semibold text-neutral-900 mb-4">Agenda de hoy</h2>
        <p class="text-sm text-neutral-500 text-center py-6">Se cargará desde la API.</p>
      </AppCard>
      <AppCard>
        <h2 class="font-semibold text-neutral-900 mb-4">Próximas reservas</h2>
        <p class="text-sm text-neutral-500 text-center py-6">Se cargará desde la API.</p>
      </AppCard>
    </div>
  </div>
</template>
