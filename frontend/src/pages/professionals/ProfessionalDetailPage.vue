<script setup>
import { useRoute } from 'vue-router'
import { Star, MapPin, Video, Clock, Calendar } from '@lucide/vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppAvatar from '@/components/ui/AppAvatar.vue'
import AppCard from '@/components/ui/AppCard.vue'

const route = useRoute()
// TODO: cargar profesional desde API GET /professionals/:id
</script>

<template>
  <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <!-- Placeholder -->
    <AppCard class="text-center py-16">
      <p class="font-medium text-neutral-700 mb-1">Perfil del profesional #{{ route.params.id }}</p>
      <p class="text-sm text-neutral-500 mb-6">Esta página cargará los datos desde la API.</p>
      <AppButton variant="primary" as="RouterLink" :to="`/book/${route.params.id}`">
        Reservar turno
      </AppButton>
    </AppCard>
  </div>
</template>
