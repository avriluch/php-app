<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter, useRoute, RouterLink } from 'vue-router'
import { User, Briefcase } from '@lucide/vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppInput from '@/components/ui/AppInput.vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { PROFESSIONAL_CATEGORIES } from '@/constants/professionalCategories'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const loading = ref(false)
const step = ref(1)
const registroAbierto = ref(true)
const plataforma = ref('ServiConnect')

const form = reactive({
  nombre: '',
  apellido: '',
  email: '',
  password: '',
  password_confirmation: '',
  role: route.query.role === 'professional' ? 'professional' : '',
  titulo: '',
  categoria: '',
})

const categories = PROFESSIONAL_CATEGORIES

const errors = reactive({ nombre: '', apellido: '', email: '', password: '', password_confirmation: '', role: '', titulo: '', categoria: '', general: '' })

const validateStep1 = () => {
  errors.role = form.role ? '' : 'Seleccioná un tipo de cuenta'
  return !errors.role
}

const validateStep2 = () => {
  errors.nombre = form.nombre.trim() ? '' : 'El nombre es requerido'
  errors.apellido = form.apellido.trim() ? '' : 'El apellido es requerido'
  errors.email = form.email ? '' : 'El email es requerido'
  errors.password = form.password.length >= 8 ? '' : 'Mínimo 8 caracteres'
  errors.password_confirmation = form.password === form.password_confirmation ? '' : 'Las contraseñas no coinciden'
  if (form.role === 'professional') {
    errors.categoria = form.categoria ? '' : 'Seleccioná una categoría'
    errors.titulo = form.titulo.trim() ? '' : 'Indicá tu título o especialidad'
  }
  return !errors.nombre && !errors.apellido && !errors.email && !errors.password && !errors.password_confirmation
    && !errors.categoria && !errors.titulo
}

const nextStep = () => {
  if (validateStep1()) step.value = 2
}

function limpiarErroresApi() {
  errors.nombre = ''
  errors.apellido = ''
  errors.email = ''
  errors.password = ''
  errors.password_confirmation = ''
  errors.general = ''
}

function aplicarErroresApi(apiErrors) {
  if (!apiErrors || typeof apiErrors !== 'object') return

  const primero = (clave) => {
    const msg = apiErrors[clave]
    return Array.isArray(msg) ? msg[0] : msg
  }

  if (primero('nombre')) errors.nombre = primero('nombre')
  if (primero('apellido')) errors.apellido = primero('apellido')
  if (primero('email')) errors.email = primero('email')
  if (primero('password')) errors.password = primero('password')
  if (primero('password_confirmation')) errors.password_confirmation = primero('password_confirmation')
  if (primero('role')) errors.role = primero('role')
  if (primero('categoria')) errors.categoria = primero('categoria')
  if (primero('titulo')) errors.titulo = primero('titulo')

  // Evitar duplicar el mismo texto bajo el campo y en "general"
  const todos = Object.values(apiErrors).flat().filter(Boolean)
  const yaMostradoEnCampo =
    errors.email || errors.nombre || errors.apellido || errors.password || errors.password_confirmation || errors.role
  if (todos.length && !yaMostradoEnCampo) errors.general = todos[0]
}

const handleSubmit = async () => {
  if (!validateStep2()) return
  loading.value = true
  limpiarErroresApi()
  try {
    await auth.register(form)
    if (form.role === 'professional') {
      router.push('/dashboard/professional/schedule')
    } else {
      router.push('/dashboard/client')
    }
  } catch (e) {
    if (e.response?.status === 422 && e.response?.data?.errors) {
      aplicarErroresApi(e.response.data.errors)
    } else {
      errors.general = e.response?.data?.message ?? 'Error al registrarse. Intentá de nuevo.'
    }
  } finally {
    loading.value = false
  }
}

const oauthLogin = (provider) => {
  // Para registrarse con Google primero hay que elegir el tipo de cuenta,
  // así el backend crea cliente o profesional según corresponda.
  if (!validateStep1()) return
  const base = import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api'
  window.location.href = `${base}/auth/${provider}/redirect?role=${form.role}`
}

onMounted(async () => {
  try {
    const { data } = await api.get('/platform-settings')
    registroAbierto.value = data.registro_abierto !== false
    plataforma.value = data.nombre_plataforma || 'ServiConnect'
  } catch {
    registroAbierto.value = true
  }
})
</script>

<template>
  <div>
    <h1 class="text-2xl font-bold text-neutral-900 mb-1">Creá tu cuenta</h1>
    <p class="text-sm text-neutral-500 mb-6">Es gratis y lleva menos de 2 minutos</p>

    <div
      v-if="!registroAbierto"
      class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
    >
      El registro en {{ plataforma }} está temporalmente cerrado. Si ya tenés cuenta, podés
      <RouterLink to="/login" class="font-medium underline">iniciar sesión</RouterLink>.
    </div>

    <!-- Step 1: tipo de cuenta -->
    <div v-if="step === 1 && registroAbierto">
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
        Continuar con Google{{ form.role === 'professional' ? ' como profesional' : form.role === 'client' ? ' como cliente' : '' }}
      </AppButton>
      <p class="text-xs text-neutral-400 text-center mt-2">
        Elegí arriba si sos cliente o profesional antes de continuar con Google.
      </p>
    </div>

    <!-- Step 2: datos personales -->
    <div v-else-if="registroAbierto">
      <button class="flex items-center gap-1 text-sm text-neutral-500 hover:text-neutral-700 mb-5 cursor-pointer" @click="step = 1">
        ← Volver
      </button>

      <form class="flex flex-col gap-4" @submit.prevent="handleSubmit">
        <div class="grid gap-4 sm:grid-cols-2">
          <AppInput
            id="nombre"
            v-model="form.nombre"
            label="Nombre"
            placeholder="Juan"
            :error="errors.nombre"
            required
          />
          <AppInput
            id="apellido"
            v-model="form.apellido"
            label="Apellido"
            placeholder="García"
            :error="errors.apellido"
            required
          />
        </div>
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

        <template v-if="form.role === 'professional'">
          <div class="flex flex-col gap-1">
            <label for="categoria" class="text-sm font-medium text-neutral-700">Categoría *</label>
            <select
              id="categoria"
              v-model="form.categoria"
              class="w-full px-3 py-2 text-sm rounded-lg border border-neutral-300 bg-white focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none"
              required
            >
              <option value="" disabled>Elegí tu rubro</option>
              <option v-for="cat in categories" :key="cat.value" :value="cat.value">
                {{ cat.emoji }} {{ cat.label }}
              </option>
            </select>
            <p v-if="errors.categoria" class="text-xs text-red-600">{{ errors.categoria }}</p>
          </div>

          <AppInput
            id="titulo"
            v-model="form.titulo"
            label="Título / especialidad *"
            placeholder="Ej: Nutricionista deportiva"
            :error="errors.titulo"
            required
          />
        </template>

        <p v-if="errors.general" class="text-sm text-red-600 text-center">{{ errors.general }}</p>
        <p
          v-if="errors.email && errors.email.includes('Google')"
          class="text-xs text-neutral-500 text-center -mt-2"
        >
          <RouterLink to="/auth/login" class="text-primary-600 font-medium">Ir al login</RouterLink>
          y usá el botón «Continuar con Google».
        </p>

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