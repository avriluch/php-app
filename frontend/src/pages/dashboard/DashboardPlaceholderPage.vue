<script setup>
import { computed } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { Construction } from '@lucide/vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppButton from '@/components/ui/AppButton.vue'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const auth = useAuthStore()

const title = computed(() => route.meta.pageTitle ?? 'Sección en desarrollo')
const description = computed(
  () =>
    route.meta.pageDescription ??
    'Esta pantalla está preparada en el menú; falta conectar la API según docs/api-contract-v0.md.',
)

const panelRoute = computed(() => {
  if (auth.role === 'professional') return '/dashboard/professional'
  if (auth.role === 'admin') return '/admin'
  return '/dashboard/client'
})
</script>

<template>
  <div>
    <h1 class="text-2xl font-bold text-neutral-900 mb-2">{{ title }}</h1>
    <p class="text-neutral-500 mb-8">{{ description }}</p>

    <AppCard class="text-center py-14">
      <div
        class="w-14 h-14 mx-auto mb-4 rounded-2xl bg-amber-50 flex items-center justify-center"
      >
        <Construction class="w-7 h-7 text-amber-600" />
      </div>
      <p class="font-medium text-neutral-800 mb-2">Próximamente</p>
      <p class="text-sm text-neutral-500 max-w-md mx-auto mb-6">
        El equipo puede implementar el endpoint del backend y luego reemplazar esta vista por la
        página definitiva.
      </p>
      <AppButton variant="outline" as="RouterLink" :to="panelRoute">Volver al panel</AppButton>
    </AppCard>
  </div>
</template>
