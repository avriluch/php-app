<script setup>
import { ref, reactive } from 'vue'
import { useRouter, useRoute, RouterLink } from 'vue-router'
import { User, Briefcase } from '@lucide/vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppInput from '@/components/ui/AppInput.vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const loading = ref(false)
const step = ref(1)

const form = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  role: route.query.role === 'professional' ? 'professional' : '',
})

const errors = reactive({ name: '', email: '', password: '', password_confirmation: '', role: '', general: '' })

const validateStep1 = () => {
  errors.role = form.role ? '' : 'Seleccioná un tipo de cuenta'
  return !errors.role
}

const validateStep2 = () => {
  errors.name = form.name.trim() ? '' : 'El nombre es requerido'
  errors.email = form.email ? '' : 'El email es requerido'
  errors.password = form.password.length >= 8 ? '' : 'Mínimo 8 caracteres'
  errors.password_confirmation = form.password === form.password_confirmation ? '' : 'Las contraseñas no coinciden'
  return !errors.name && !errors.email && !errors.password && !errors.password_confirmation
}

const nextStep = () => {
  if (validateStep1()) step.value = 2
}

const handleSubmit = async () => {
  if (!validateStep2()) return
  loading.value = true
  errors.general = ''
  try {
    await auth.register(form)
    if (form.role === 'professional') {
      router.push('/dashboard/professional')
    } else {
      router.push('/dashboard/client')
    }
  } catch (e) {
    errors.general = e.response?.data?.message ?? 'Error al registrarse. Intentá de nuevo.'
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
    <h1 class="text-2xl font-bold text-neutral-900 mb-1">Creá tu cuenta</h1>
    <p class="text-sm text-neutral-500 mb-6">Es gratis y lleva menos de 2 minutos</p>

    <!-- Step 1: tipo de cuenta -->
    <div v-if="step === 1">
      <p class="text-sm font-medium text-neutral-700 mb-3">¿Cómo vas a usar ServiConnect?</p>

      <div class="grid grid-cols-2 gap-3 mb-6">
        <button
          :class="[
            'flex flex-col items-center gap-3 p-5 rounded-xl border-2 transition-all cursor-pointer',
            form.role === 'client'
              ? 'border-primary-500 bg-primary-50 text-primary-700'
              : 'border-neutral-200 hover:border-primary-300 text-neutral-600',
          ]"
          @click="form.role = 'client'"
        >
          <User class="w-7 h-7" />
          <div class="text-center">
            <p class="font-semibold text-sm">Soy cliente</p>
            <p class="text-xs mt-0.5 opacity-70">Quiero reservar servicios</p>
          </div>
        </button>

        <button
          :class="[
            'flex flex-col items-center gap-3 p-5 rounded-xl border-2 transition-all cursor-pointer',
            form.role === 'professional'
              ? 'border-primary-500 bg-primary-50 text-primary-700'
              : 'border-neutral-200 hover:border-primary-300 text-neutral-600',
          ]"
          @click="form.role = 'professional'"
        >
          <Briefcase class="w-7 h-7" />
          <div class="text-center">
            <p class="font-semibold text-sm">Soy profesional</p>
            <p class="text-xs mt-0.5 opacity-70">Quiero ofrecer servicios</p>
          </div>
        </button>
      </div>

      <p v-if="errors.role" class="text-sm text-red-600 mb-4">{{ errors.role }}</p>

      <AppButton variant="primary" size="lg" class="w-full" @click="nextStep">
        Continuar
      </AppButton>

      <div class="flex items-center gap-3 my-5">
        <hr class="flex-1 border-neutral-200" />
        <span class="text-xs text-neutral-400">o usá</span>
        <hr class="flex-1 border-neutral-200" />
      </div>

      <AppButton variant="outline" size="md" class="w-full" @click="oauthLogin('google')">
        <img src="https://www.google.com/favicon.ico" class="w-4 h-4" alt="Google" />
        Continuar con Google
      </AppButton>
    </div>

    <!-- Step 2: datos personales -->
    <div v-else>
      <button class="flex items-center gap-1 text-sm text-neutral-500 hover:text-neutral-700 mb-5 cursor-pointer" @click="step = 1">
        ← Volver
      </button>

      <form class="flex flex-col gap-4" @submit.prevent="handleSubmit">
        <AppInput
          id="name"
          v-model="form.name"
          label="Nombre completo"
          placeholder="Juan García"
          :error="errors.name"
          required
        />
        <AppInput
          id="email"
          v-model="form.email"
          label="Email"
          type="email"
          placeholder="tu@email.com"
          :error="errors.email"
          required
        />
        <AppInput
          id="password"
          v-model="form.password"
          label="Contraseña"
          type="password"
          placeholder="Mínimo 8 caracteres"
          :error="errors.password"
          required
        />
        <AppInput
          id="password_confirmation"
          v-model="form.password_confirmation"
          label="Confirmá la contraseña"
          type="password"
          placeholder="Repetí tu contraseña"
          :error="errors.password_confirmation"
          required
        />

        <p v-if="errors.general" class="text-sm text-red-600 text-center">{{ errors.general }}</p>

        <AppButton type="submit" variant="primary" size="lg" class="w-full" :loading="loading">
          Crear cuenta
        </AppButton>

        <p class="text-xs text-neutral-400 text-center">
          Al registrarte aceptás nuestros
          <a href="#" class="text-primary-600">Términos y condiciones</a> y
          <a href="#" class="text-primary-600">Política de privacidad</a>.
        </p>
      </form>
    </div>

    <p class="text-center text-sm text-neutral-500 mt-6">
      ¿Ya tenés cuenta?
      <RouterLink to="/auth/login" class="text-primary-600 font-medium hover:text-primary-700">Ingresá</RouterLink>
    </p>
  </div>
</template>
