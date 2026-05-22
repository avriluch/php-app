<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import AppCard from '@/components/ui/AppCard.vue'
import AppAvatar from '@/components/ui/AppAvatar.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppButton from '@/components/ui/AppButton.vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const loading = ref(false)
const loadError = ref(null)

const roleLabels = {
  client: 'Cliente',
  professional: 'Profesional',
  admin: 'Administrador',
}

const dashboardRoute = () => {
  if (auth.role === 'professional') return '/dashboard/professional'
  if (auth.role === 'admin') return '/admin'
  return '/dashboard/client'
}

onMounted(async () => {
  if (!auth.isLoggedIn) return
  loading.value = true
  loadError.value = null
  try {
    await auth.fetchMe()
  } catch {
    loadError.value = 'No se pudieron cargar los datos. Cerrá sesión e ingresá de nuevo.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="max-w-2xl mx-auto">
    <div class="mb-8">
      <h1 class="text-2xl font-bold text-neutral-900">Mi perfil</h1>
      <p class="text-neutral-500 mt-1">Datos de tu cuenta en la plataforma.</p>
    </div>

    <p v-if="loading" class="text-neutral-500 text-sm">Cargando perfil…</p>
    <p v-else-if="loadError" class="text-red-600 text-sm mb-4">{{ loadError }}</p>

    <AppCard v-else-if="auth.user?.nombre" padding="lg">
      <div class="flex flex-col sm:flex-row items-center sm:items-start gap-6 mb-6">
        <AppAvatar :name="auth.displayName" :src="auth.user.foto_perfil" size="xl" />
        <div class="text-center sm:text-left">
          <h2 class="text-xl font-semibold text-neutral-900">{{ auth.displayName }}</h2>
          <p class="text-neutral-500">{{ auth.user.email }}</p>
          <AppBadge variant="primary" class="mt-2">
            {{ roleLabels[auth.role] ?? auth.role }}
          </AppBadge>
        </div>
      </div>

      <dl class="grid gap-4 sm:grid-cols-2 text-sm">
        <div>
          <dt class="text-neutral-500">Nombre</dt>
          <dd class="font-medium text-neutral-900">{{ auth.user.nombre }}</dd>
        </div>
        <div>
          <dt class="text-neutral-500">Apellido</dt>
          <dd class="font-medium text-neutral-900">{{ auth.user.apellido }}</dd>
        </div>
        <div v-if="auth.user.telefono">
          <dt class="text-neutral-500">Teléfono</dt>
          <dd class="font-medium text-neutral-900">{{ auth.user.telefono }}</dd>
        </div>
      </dl>

      <p class="text-xs text-neutral-400 mt-6">
        La edición del perfil se conectará con la API en una próxima versión.
      </p>
    </AppCard>

    <AppButton variant="outline" class="mt-6" as="RouterLink" :to="dashboardRoute()">
      Volver al panel
    </AppButton>
  </div>
</template>
