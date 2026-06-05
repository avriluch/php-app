<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount } from 'vue'
import { RouterLink } from 'vue-router'
import { CheckCircle, Camera, Trash2 } from '@lucide/vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppAvatar from '@/components/ui/AppAvatar.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppInput from '@/components/ui/AppInput.vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const loading = ref(false)
const saving = ref(false)
const avatarLoading = ref(false)
const avatarInput = ref(null)
const avatarPreview = ref(null)
const loadError = ref(null)
const success = ref(false)
const changePassword = ref(false)

const avatarSrc = computed(() => avatarPreview.value ?? auth.user?.foto_perfil ?? null)
const tieneFoto = computed(() => Boolean(avatarSrc.value))

const form = reactive({
  nombre: '',
  apellido: '',
  email: '',
  telefono: '',
})

const passwordForm = reactive({
  password: '',
  password_confirmation: '',
  password_actual: '',
})

const errors = reactive({
  nombre: '',
  apellido: '',
  email: '',
  telefono: '',
  password: '',
  password_confirmation: '',
  password_actual: '',
  avatar: '',
  general: '',
})

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

function fillFormFromUser() {
  const u = auth.user
  if (!u) return
  form.nombre = u.nombre ?? ''
  form.apellido = u.apellido ?? ''
  form.email = u.email ?? ''
  form.telefono = u.telefono ?? ''
}

function limpiarErrores() {
  Object.keys(errors).forEach((k) => { errors[k] = '' })
  success.value = false
}

function aplicarErroresApi(apiErrors) {
  if (!apiErrors || typeof apiErrors !== 'object') return
  const primero = (clave) => {
    const msg = apiErrors[clave]
    return Array.isArray(msg) ? msg[0] : msg
  }
  for (const clave of ['nombre', 'apellido', 'email', 'telefono', 'password', 'password_confirmation', 'password_actual']) {
    if (primero(clave)) errors[clave] = primero(clave)
  }
  const todos = Object.values(apiErrors).flat().filter(Boolean)
  const yaEnCampo = Object.entries(errors).some(([k, v]) => k !== 'general' && v)
  if (todos.length && !yaEnCampo) errors.general = todos[0]
}

function validar() {
  limpiarErrores()
  let ok = true
  if (!form.nombre.trim()) {
    errors.nombre = 'El nombre es requerido.'
    ok = false
  }
  if (!form.apellido.trim()) {
    errors.apellido = 'El apellido es requerido.'
    ok = false
  }
  if (!form.email.trim()) {
    errors.email = 'El email es requerido.'
    ok = false
  }
  if (changePassword.value) {
    if (!passwordForm.password_actual) {
      errors.password_actual = 'Ingresá tu contraseña actual.'
      ok = false
    }
    if (passwordForm.password.length < 6) {
      errors.password = 'La nueva contraseña debe tener al menos 6 caracteres.'
      ok = false
    }
    if (passwordForm.password !== passwordForm.password_confirmation) {
      errors.password_confirmation = 'Las contraseñas no coinciden.'
      ok = false
    }
  }
  return ok
}

async function handleSubmit() {
  if (!validar()) return
  saving.value = true
  limpiarErrores()
  try {
    const payload = {
      nombre: form.nombre.trim(),
      apellido: form.apellido.trim(),
      email: form.email.trim(),
      telefono: form.telefono.trim() || null,
    }
    if (changePassword.value) {
      payload.password = passwordForm.password
      payload.password_confirmation = passwordForm.password_confirmation
      payload.password_actual = passwordForm.password_actual
    }
    await auth.updateProfile(payload)
    fillFormFromUser()
    passwordForm.password = ''
    passwordForm.password_confirmation = ''
    passwordForm.password_actual = ''
    changePassword.value = false
    success.value = true
  } catch (e) {
    if (e.response?.status === 422) {
      aplicarErroresApi(e.response.data.errors)
      if (e.response.data.message && !errors.general) {
        errors.general = e.response.data.message
      }
    } else {
      errors.general = e.response?.data?.message ?? 'No se pudo guardar el perfil.'
    }
  } finally {
    saving.value = false
  }
}

function handleCancel() {
  revocarPreview()
  fillFormFromUser()
  changePassword.value = false
  passwordForm.password = ''
  passwordForm.password_confirmation = ''
  passwordForm.password_actual = ''
  limpiarErrores()
}

function revocarPreview() {
  if (avatarPreview.value?.startsWith('blob:')) {
    URL.revokeObjectURL(avatarPreview.value)
  }
  avatarPreview.value = null
}

function abrirSelectorFoto() {
  avatarInput.value?.click()
}

async function onAvatarSelected(event) {
  const file = event.target.files?.[0]
  event.target.value = ''
  if (!file) return

  errors.avatar = ''
  const tipos = ['image/jpeg', 'image/png', 'image/webp']
  if (!tipos.includes(file.type)) {
    errors.avatar = 'Usá una imagen JPG, PNG o WebP.'
    return
  }
  if (file.size > 2 * 1024 * 1024) {
    errors.avatar = 'La imagen no puede superar 2 MB.'
    return
  }

  revocarPreview()
  avatarPreview.value = URL.createObjectURL(file)

  avatarLoading.value = true
  try {
    await auth.uploadAvatar(file)
    if (!auth.user?.foto_perfil) {
      await auth.fetchMe()
    }
    revocarPreview()
    success.value = true
  } catch (e) {
    revocarPreview()
    errors.avatar = e.response?.data?.message
      ?? e.response?.data?.errors?.avatar?.[0]
      ?? 'No se pudo subir la foto.'
  } finally {
    avatarLoading.value = false
  }
}

async function quitarFoto() {
  if (!tieneFoto.value || avatarLoading.value) return
  avatarLoading.value = true
  errors.avatar = ''
  try {
    await auth.removeAvatar()
    revocarPreview()
    success.value = true
  } catch (e) {
    errors.avatar = e.response?.data?.message ?? 'No se pudo quitar la foto.'
  } finally {
    avatarLoading.value = false
  }
}

onBeforeUnmount(revocarPreview)

onMounted(async () => {
  if (!auth.isLoggedIn) return
  loading.value = true
  loadError.value = null
  try {
    await auth.fetchMe()
    fillFormFromUser()
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
      <p class="text-neutral-500 mt-1">Editá los datos de tu cuenta.</p>
    </div>

    <p v-if="loading" class="text-neutral-500 text-sm">Cargando perfil…</p>
    <p v-else-if="loadError" class="text-red-600 text-sm mb-4">{{ loadError }}</p>

    <template v-else-if="auth.user?.nombre">
      <div
        v-if="success"
        class="mb-4 flex items-center gap-2 rounded-lg border border-accent-200 bg-accent-50 px-4 py-3 text-sm text-accent-800"
      >
        <CheckCircle class="w-4 h-4 shrink-0" />
        Perfil actualizado correctamente.
      </div>

      <AppCard padding="lg">
        <div class="flex flex-col sm:flex-row items-center sm:items-start gap-6 mb-8 pb-6 border-b border-neutral-100">
          <div class="relative shrink-0">
            <AppAvatar
              :key="avatarSrc || 'sin-foto'"
              :name="auth.displayName"
              :src="avatarSrc"
              size="2xl"
            />
            <div
              v-if="avatarLoading"
              class="absolute inset-0 rounded-full bg-black/40 flex items-center justify-center"
            >
              <span class="text-white text-xs font-medium">Subiendo…</span>
            </div>
          </div>
          <div class="text-center sm:text-left flex-1">
            <h2 class="text-xl font-semibold text-neutral-900">{{ auth.displayName }}</h2>
            <AppBadge variant="primary" class="mt-2">
              {{ roleLabels[auth.role] ?? auth.role }}
            </AppBadge>
            <p class="text-xs text-neutral-400 mt-2">El rol no se puede cambiar desde acá.</p>

            <div class="flex flex-wrap gap-2 mt-4 justify-center sm:justify-start">
              <input
                ref="avatarInput"
                type="file"
                accept="image/jpeg,image/png,image/webp"
                class="hidden"
                @change="onAvatarSelected"
              />
              <AppButton
                type="button"
                variant="outline"
                size="sm"
                :disabled="avatarLoading || saving"
                @click="abrirSelectorFoto"
              >
                <Camera class="w-4 h-4" /> Cambiar foto
              </AppButton>
              <AppButton
                v-if="tieneFoto"
                type="button"
                variant="outline"
                size="sm"
                :disabled="avatarLoading || saving"
                @click="quitarFoto"
              >
                <Trash2 class="w-4 h-4" /> Quitar foto
              </AppButton>
            </div>
            <p v-if="errors.avatar" class="text-sm text-red-600 mt-2">{{ errors.avatar }}</p>
            <p v-else class="text-xs text-neutral-400 mt-2">JPG, PNG o WebP · máximo 2 MB</p>
          </div>
        </div>

        <form class="space-y-4" @submit.prevent="handleSubmit">
          <div class="grid gap-4 sm:grid-cols-2">
            <AppInput
              id="nombre"
              v-model="form.nombre"
              label="Nombre"
              required
              :error="errors.nombre"
            />
            <AppInput
              id="apellido"
              v-model="form.apellido"
              label="Apellido"
              required
              :error="errors.apellido"
            />
          </div>

          <AppInput
            id="email"
            v-model="form.email"
            type="email"
            label="Email"
            required
            :error="errors.email"
          />

          <AppInput
            id="telefono"
            v-model="form.telefono"
            type="tel"
            label="Teléfono"
            placeholder="Opcional"
            :error="errors.telefono"
          />

          <div class="pt-4 border-t border-neutral-100">
            <label class="flex items-center gap-2 text-sm font-medium text-neutral-700 cursor-pointer">
              <input
                v-model="changePassword"
                type="checkbox"
                class="rounded border-neutral-300 text-primary-600 focus:ring-primary-500"
              />
              Cambiar contraseña
            </label>
            <p class="text-xs text-neutral-400 mt-1 ml-6">
              Solo si ingresás con email y contraseña (no con Google).
            </p>

            <div v-if="changePassword" class="mt-4 space-y-4 pl-0 sm:pl-6">
              <AppInput
                id="password_actual"
                v-model="passwordForm.password_actual"
                type="password"
                label="Contraseña actual"
                required
                :error="errors.password_actual"
              />
              <AppInput
                id="password"
                v-model="passwordForm.password"
                type="password"
                label="Nueva contraseña"
                hint="Mínimo 6 caracteres"
                required
                :error="errors.password"
              />
              <AppInput
                id="password_confirmation"
                v-model="passwordForm.password_confirmation"
                type="password"
                label="Confirmar nueva contraseña"
                required
                :error="errors.password_confirmation"
              />
            </div>
          </div>

          <p v-if="errors.general" class="text-sm text-red-600">{{ errors.general }}</p>

          <div class="flex flex-wrap gap-3 pt-2">
            <AppButton type="submit" variant="primary" :loading="saving">
              Guardar cambios
            </AppButton>
            <AppButton type="button" variant="outline" :disabled="saving" @click="handleCancel">
              Descartar
            </AppButton>
          </div>
        </form>
      </AppCard>
    </template>

    <AppButton variant="outline" class="mt-6" as="RouterLink" :to="dashboardRoute()">
      Volver al panel
    </AppButton>
  </div>
</template>
