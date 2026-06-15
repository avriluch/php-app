<script setup>
import { ref, reactive, onMounted } from 'vue'
import { Save, Globe, Shield, CalendarClock } from '@lucide/vue'
import api from '@/services/api'
import { useUIStore } from '@/stores/ui'
import AppCard from '@/components/ui/AppCard.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppSpinner from '@/components/ui/AppSpinner.vue'

const ui = useUIStore()

const loading = ref(true)
const guardando = ref(false)
const error = ref(null)
const actualizado = ref(null)

const formulario = reactive({
  nombre_plataforma: '',
  email_soporte: '',
  mensaje_mantenimiento: '',
  registro_abierto: true,
  mantenimiento_activo: false,
  recordatorio_horas_antes: 24,
  antelacion_reserva_min_horas: 0,
})

const errores = reactive({})

function limpiarErrores() {
  Object.keys(errores).forEach((k) => {
    errores[k] = ''
  })
}

async function cargar() {
  loading.value = true
  error.value = null
  try {
    const { data } = await api.get('/admin/settings')
    formulario.nombre_plataforma = data.nombre_plataforma ?? ''
    formulario.email_soporte = data.email_soporte ?? ''
    formulario.mensaje_mantenimiento = data.mensaje_mantenimiento ?? ''
    formulario.registro_abierto = Boolean(data.registro_abierto)
    formulario.mantenimiento_activo = Boolean(data.mantenimiento_activo)
    formulario.recordatorio_horas_antes = data.recordatorio_horas_antes ?? 24
    formulario.antelacion_reserva_min_horas = data.antelacion_reserva_min_horas ?? 0
    actualizado.value = data.updated_at ?? null
  } catch (e) {
    error.value = e.response?.data?.message ?? 'No se pudo cargar la configuración.'
  } finally {
    loading.value = false
  }
}

async function guardar() {
  guardando.value = true
  limpiarErrores()
  try {
    const { data } = await api.put('/admin/settings', {
      nombre_plataforma: formulario.nombre_plataforma.trim(),
      email_soporte: formulario.email_soporte.trim() || null,
      mensaje_mantenimiento: formulario.mensaje_mantenimiento.trim() || null,
      registro_abierto: formulario.registro_abierto,
      mantenimiento_activo: formulario.mantenimiento_activo,
      recordatorio_horas_antes: Number(formulario.recordatorio_horas_antes),
      antelacion_reserva_min_horas: Number(formulario.antelacion_reserva_min_horas),
    })
    actualizado.value = data.settings?.updated_at ?? null
    ui.toast.success('Configuración guardada.')
  } catch (e) {
    if (e.response?.status === 422 && e.response.data?.errors) {
      Object.assign(errores, e.response.data.errors)
    }
    ui.toast.error(e.response?.data?.message ?? 'No se pudo guardar.')
  } finally {
    guardando.value = false
  }
}

onMounted(cargar)
</script>

<template>
  <div class="max-w-3xl">
    <div class="mb-8">
      <h1 class="text-2xl font-bold text-neutral-900">Configuración del sistema</h1>
      <p class="text-neutral-500 mt-1 text-sm">
        Parámetros globales de la plataforma: acceso, reservas y comunicaciones.
      </p>
      <p v-if="actualizado" class="text-xs text-neutral-400 mt-2">
        Última actualización: {{ new Date(actualizado).toLocaleString('es-UY') }}
      </p>
    </div>

    <div v-if="loading" class="flex justify-center py-16">
      <AppSpinner size="lg" />
    </div>

    <p v-else-if="error" class="text-red-600 text-sm">{{ error }}</p>

    <form v-else class="space-y-6" @submit.prevent="guardar">
      <AppCard padding="md">
        <h2 class="font-semibold text-neutral-900 mb-4 flex items-center gap-2">
          <Globe class="w-5 h-5 text-neutral-500" /> General
        </h2>

        <AppInput
          v-model="formulario.nombre_plataforma"
          label="Nombre de la plataforma"
          hint="Se muestra en emails y mensajes públicos."
          :error="errores.nombre_plataforma?.[0]"
          required
        />

        <AppInput
          v-model="formulario.email_soporte"
          type="email"
          label="Email de soporte"
          hint="Contacto para usuarios (opcional)."
          :error="errores.email_soporte?.[0]"
          class="mt-4"
        />
      </AppCard>

      <AppCard padding="md">
        <h2 class="font-semibold text-neutral-900 mb-4 flex items-center gap-2">
          <Shield class="w-5 h-5 text-neutral-500" /> Acceso
        </h2>

        <label class="flex items-start gap-3 cursor-pointer">
          <input
            v-model="formulario.registro_abierto"
            type="checkbox"
            class="mt-1 rounded border-neutral-300 text-primary-600 focus:ring-primary-500"
          />
          <span>
            <span class="block text-sm font-medium text-neutral-900">Registro abierto</span>
            <span class="block text-xs text-neutral-500 mt-0.5">
              Permitir que nuevos usuarios creen cuenta.
            </span>
          </span>
        </label>

        <label class="flex items-start gap-3 cursor-pointer mt-4">
          <input
            v-model="formulario.mantenimiento_activo"
            type="checkbox"
            class="mt-1 rounded border-neutral-300 text-primary-600 focus:ring-primary-500"
          />
          <span>
            <span class="block text-sm font-medium text-neutral-900">Modo mantenimiento</span>
            <span class="block text-xs text-neutral-500 mt-0.5">
              Bloquea la API para usuarios no admin (login de admin sigue disponible).
            </span>
          </span>
        </label>

        <div class="mt-4">
          <label class="block text-sm font-medium text-neutral-700 mb-1">
            Mensaje de mantenimiento
          </label>
          <textarea
            v-model="formulario.mensaje_mantenimiento"
            rows="3"
            placeholder="Ej: Estamos actualizando el sistema. Volvé en unos minutos."
            class="w-full px-3 py-2 text-sm rounded-lg border border-neutral-300 bg-white outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 placeholder:text-neutral-400 resize-y"
          />
          <p v-if="errores.mensaje_mantenimiento?.[0]" class="text-xs text-red-600 mt-1">
            {{ errores.mensaje_mantenimiento[0] }}
          </p>
        </div>
      </AppCard>

      <AppCard padding="md">
        <h2 class="font-semibold text-neutral-900 mb-4 flex items-center gap-2">
          <CalendarClock class="w-5 h-5 text-neutral-500" /> Reservas y recordatorios
        </h2>

        <AppInput
          v-model="formulario.recordatorio_horas_antes"
          type="number"
          label="Recordatorio (horas antes del turno)"
          hint="Usado por el comando automático de recordatorios."
          :error="errores.recordatorio_horas_antes?.[0]"
          required
        />

        <AppInput
          v-model="formulario.antelacion_reserva_min_horas"
          type="number"
          label="Antelación mínima para reservar (horas)"
          hint="Los clientes no verán turnos antes de ese margen."
          :error="errores.antelacion_reserva_min_horas?.[0]"
          required
          class="mt-4"
        />
      </AppCard>

      <div class="flex justify-end">
        <AppButton type="submit" variant="primary" :loading="guardando">
          <Save class="w-4 h-4" /> Guardar cambios
        </AppButton>
      </div>
    </form>
  </div>
</template>
