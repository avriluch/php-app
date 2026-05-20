<script setup>
import { Users, Briefcase, BarChart2, AlertCircle } from '@lucide/vue'
import AppCard from '@/components/ui/AppCard.vue'

const stats = [
  { label: 'Usuarios registrados', value: '—', icon: Users, color: 'text-primary-600 bg-primary-50' },
  { label: 'Profesionales activos', value: '—', icon: Briefcase, color: 'text-accent-600 bg-accent-50' },
  { label: 'Reservas este mes', value: '—', icon: BarChart2, color: 'text-purple-600 bg-purple-50' },
  { label: 'Incidencias abiertas', value: '—', icon: AlertCircle, color: 'text-red-600 bg-red-50' },
]
</script>

<template>
  <div>
    <div class="mb-8">
      <h1 class="text-2xl font-bold text-neutral-900">Panel administrativo</h1>
      <p class="text-neutral-500 mt-1">Monitoreo general del sistema.</p>
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

    <AppCard>
      <p class="text-sm text-neutral-500 text-center py-8">
        Gestión de usuarios, profesionales y métricas se implementarán aquí.
      </p>
    </AppCard>
  </div>
</template>
