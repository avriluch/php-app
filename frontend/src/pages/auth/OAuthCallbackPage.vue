<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppSpinner from '@/components/ui/AppSpinner.vue'
import AppButton from '@/components/ui/AppButton.vue'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const error = ref(null)

onMounted(async () => {
  const err = route.query.error
  if (err) {
    error.value = typeof err === 'string' ? err : 'Error al iniciar sesión con Google.'
    return
  }

  const token = route.query.token
  if (!token || typeof token !== 'string') {
    error.value = 'No se recibió el token de autenticación.'
    return
  }

  try {
    auth.setToken(token)
    await auth.fetchMe()
    if (auth.role === 'professional') {
      router.replace('/dashboard/professional')
    } else if (auth.role === 'admin') {
      router.replace('/admin')
    } else {
      router.replace('/dashboard/client')
    }
  } catch {
    error.value = 'No se pudo completar el inicio de sesión.'
    auth.logout()
  }
})
</script>

<template>
  <div class="text-center py-12">
    <AppSpinner v-if="!error" size="lg" class="mx-auto mb-4" />
    <p v-if="!error" class="text-neutral-600 text-sm">Completando inicio con Google…</p>
    <template v-else>
      <p class="text-red-600 text-sm mb-4">{{ error }}</p>
      <AppButton variant="primary" as="RouterLink" to="/auth/login">Volver al login</AppButton>
    </template>
  </div>
</template>
