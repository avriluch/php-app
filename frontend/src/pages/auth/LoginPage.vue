<script setup>
import { ref, reactive } from 'vue'
import { useRouter, useRoute, RouterLink } from 'vue-router'
import { Mail, Lock } from '@lucide/vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppInput from '@/components/ui/AppInput.vue'
import { useAuthStore } from '@/stores/auth'
import { useUIStore } from '@/stores/ui'

const auth = useAuthStore()
const ui = useUIStore()
const router = useRouter()
const route = useRoute()

const loading = ref(false)
const form = reactive({ email: '', password: '' })
const errors = reactive({ email: '', password: '', general: '' })

const validate = () => {
  errors.email = form.email ? '' : 'El email es requerido'
  errors.password = form.password ? '' : 'La contraseña es requerida'
  return !errors.email && !errors.password
}

const handleSubmit = async () => {
  if (!validate()) return
  loading.value = true
  errors.general = ''
  try {
    await auth.login(form)
    const redirect = route.query.redirect ?? null
    if (redirect) {
      router.push(redirect)
    } else if (auth.role === 'professional') {
      router.push('/dashboard/professional')
    } else if (auth.role === 'admin') {
      router.push('/admin')
    } else {
      router.push('/dashboard/client')
    }
  } catch (e) {
    errors.general = e.response?.data?.message ?? 'Email o contraseña incorrectos'
  } finally {
    loading.value = false
  }
}

const oauthLogin = (provider) => {
  window.location.href = `${import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api'}/auth/${provider}/redirect`
}
</script>

<template>
  <div>
    <h1 class="text-2xl font-bold text-neutral-900 mb-1">Bienvenido de vuelta</h1>
    <p class="text-sm text-neutral-500 mb-6">Ingresá a tu cuenta para continuar</p>

    <!-- OAuth -->
    <div class="mb-6">
      <AppButton variant="outline" size="md" @click="oauthLogin('google')" class="w-full">
        <img src="https://www.google.com/favicon.ico" class="w-4 h-4" alt="Google" />
        Continuar con Google
      </AppButton>
    </div>

    <div class="flex items-center gap-3 mb-6">
      <hr class="flex-1 border-neutral-200" />
      <span class="text-xs text-neutral-400">o ingresá con email</span>
      <hr class="flex-1 border-neutral-200" />
    </div>

    <!-- Form -->
    <form class="flex flex-col gap-4" @submit.prevent="handleSubmit">
      <AppInput
        id="email"
        v-model="form.email"
        label="Email"
        type="email"
        placeholder="tu@email.com"
        :error="errors.email"
        required
      />
      <div>
        <AppInput
          id="password"
          v-model="form.password"
          label="Contraseña"
          type="password"
          placeholder="••••••••"
          :error="errors.password"
          required
        />
        <div class="text-right mt-1">
          <a href="#" class="text-xs text-primary-600 hover:text-primary-700">¿Olvidaste tu contraseña?</a>
        </div>
      </div>

      <p v-if="errors.general" class="text-sm text-red-600 text-center">{{ errors.general }}</p>

      <AppButton type="submit" variant="primary" size="lg" class="w-full" :loading="loading">
        Ingresar
      </AppButton>
    </form>

    <p class="text-center text-sm text-neutral-500 mt-6">
      ¿No tenés cuenta?
      <RouterLink to="/auth/register" class="text-primary-600 font-medium hover:text-primary-700">Registrate</RouterLink>
    </p>
  </div>
</template>
