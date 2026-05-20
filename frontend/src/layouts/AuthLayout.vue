<script setup>
import { RouterLink } from 'vue-router'
</script>

<template>
  <div class="min-h-screen flex flex-col items-center justify-center bg-neutral-50 px-4">

    <!-- Logo -->
    <RouterLink to="/" class="flex items-center gap-2 mb-8 no-underline">
      <span class="w-9 h-9 bg-primary-600 rounded-xl flex items-center justify-center">
        <span class="text-white font-bold">S</span>
      </span>
      <span class="font-bold text-xl text-neutral-900">ServiConnect</span>
    </RouterLink>

    <!-- Card -->
    <div class="w-full max-w-md bg-white rounded-2xl border border-neutral-200 p-8" style="box-shadow: var(--shadow-modal)">
      <RouterView />
    </div>

    <!-- Footer minimal -->
    <p class="mt-6 text-xs text-neutral-400">
      © {{ new Date().getFullYear() }} ServiConnect
    </p>
  </div>
</template>
