<script setup>
import { RouterLink } from 'vue-router'
</script>

<template>
  <footer class="bg-neutral-900 text-neutral-400 mt-auto">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-8">

        <!-- Brand -->
        <div class="md:col-span-2">
          <div class="flex items-center gap-2 mb-3">
            <span class="w-8 h-8 bg-primary-600 rounded-lg flex items-center justify-center">
              <span class="text-white font-bold text-sm">S</span>
            </span>
            <span class="font-bold text-lg text-white">ServiConnect</span>
          </div>
          <p class="text-sm leading-relaxed max-w-xs">
            La plataforma para conectar profesionales independientes con sus clientes, de forma simple y eficiente.
          </p>
        </div>

        <!-- Plataforma -->
        <div>
          <h3 class="text-white font-semibold text-sm mb-3">Plataforma</h3>
          <ul class="space-y-2 text-sm">
            <li><RouterLink to="/professionals" class="hover:text-white transition-colors no-underline text-neutral-400">Explorar profesionales</RouterLink></li>
            <li><RouterLink to="/auth/register?role=professional" class="hover:text-white transition-colors no-underline text-neutral-400">Soy profesional</RouterLink></li>
          </ul>
        </div>

        <!-- Soporte -->
        <div>
          <h3 class="text-white font-semibold text-sm mb-3">Soporte</h3>
          <ul class="space-y-2 text-sm">
            <li><a href="#" class="hover:text-white transition-colors no-underline text-neutral-400">Centro de ayuda</a></li>
            <li><a href="#" class="hover:text-white transition-colors no-underline text-neutral-400">Términos y condiciones</a></li>
            <li><a href="#" class="hover:text-white transition-colors no-underline text-neutral-400">Política de privacidad</a></li>
          </ul>
        </div>
      </div>

      <div class="mt-10 pt-6 border-t border-neutral-800 text-xs text-center">
        © {{ new Date().getFullYear() }} ServiConnect. Todos los derechos reservados.
      </div>
    </div>
  </footer>
</template>
