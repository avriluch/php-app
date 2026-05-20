<script setup>
import { ref } from 'vue'
import { Search, SlidersHorizontal } from '@lucide/vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppAvatar from '@/components/ui/AppAvatar.vue'
import AppSpinner from '@/components/ui/AppSpinner.vue'

// TODO: conectar con API GET /professionals
const loading = ref(false)
const search = ref('')
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="mb-8">
      <h1 class="text-3xl font-bold text-neutral-900 mb-2">Profesionales</h1>
      <p class="text-neutral-500">Encontrá al profesional ideal para tus necesidades</p>
    </div>

    <!-- Barra de búsqueda y filtros -->
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
      <AppButton variant="outline" size="md">
        <SlidersHorizontal class="w-4 h-4" /> Filtros
      </AppButton>
    </div>

    <!-- Placeholder -->
    <div v-if="loading" class="flex justify-center py-20">
      <AppSpinner size="lg" />
    </div>
    <div v-else class="flex flex-col items-center justify-center py-20 text-center">
      <div class="w-16 h-16 bg-neutral-100 rounded-2xl flex items-center justify-center mb-4">
        <Search class="w-7 h-7 text-neutral-400" />
      </div>
      <p class="font-medium text-neutral-700 mb-1">Listado de profesionales</p>
      <p class="text-sm text-neutral-500">Esta página se conectará con la API del backend.</p>
    </div>
  </div>
</template>
