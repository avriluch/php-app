<script setup>
import { RouterLink } from 'vue-router'
import AppButton from '@/components/ui/AppButton.vue'
</script>

<template>
  <div class="min-h-screen bg-neutral-50 flex items-center justify-center px-4">
    <div class="text-center">
      <p class="text-8xl font-bold text-primary-200 mb-4">404</p>
      <h1 class="text-2xl font-bold text-neutral-900 mb-2">Página no encontrada</h1>
      <p class="text-neutral-500 mb-8">La página que buscás no existe o fue movida.</p>
      <AppButton variant="primary" as="RouterLink" to="/">
        Volver al inicio
      </AppButton>
    </div>
  </div>
</template>
