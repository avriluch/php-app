<script setup>
import { ref, reactive, onMounted } from 'vue'
import api from '@/services/api'
import { useUIStore } from '@/stores/ui'
import AppCard from '@/components/ui/AppCard.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppSpinner from '@/components/ui/AppSpinner.vue'

const ui = useUIStore()

const loading = ref(true)
const saving = ref(false)
const error = ref(null)

const form = reactive({
  titulo: '',
  descripcion: '',
  cancelacion_horas_minimas: 0,
})
const errors = reactive({ titulo: '', cancelacion_horas_minimas: '' })

async function load() {
  loading.value = true
  error.value = null
  try {
    const { data } = await api.get('/professional/profile')
    form.titulo = data.titulo ?? ''
    form.descripcion = data.descripcion ?? ''
    form.cancelacion_horas_minimas = data.cancelacion_horas_minimas ?? 0
  } catch (e) {
    error.value = e.response?.data?.message ?? 'No se pudo cargar el perfil.'
  } finally {
    loading.value = false
  }
}

function validar() {
  errors.titulo = ''
  errors.cancelacion_horas_minimas = ''
  let ok = true
  if (!form.titulo.trim()) {
    errors.titulo = 'El título es requerido.'
    ok = false
  }
  const horas = Number(form.cancelacion_horas_minimas)
  if (!Number.isInteger(horas) || horas < 0 || horas > 168) {
    errors.cancelacion_horas_minimas = 'Debe ser un número entre 0 y 168.'
    ok = false
  }
  return ok
}

async function guardar() {
  if (!validar()) return
  saving.value = true
  try {
    await api.put('/professional/profile', {
      titulo: form.titulo.trim(),
      descripcion: form.descripcion.trim() || null,
      cancelacion_horas_minimas: Number(form.cancelacion_horas_minimas),
    })
    ui.toast.success('Perfil actualizado.')
  } catch (e) {
    if (e.response?.status === 422 && e.response.data?.errors) {
      const apiErrors = e.response.data.errors
      errors.titulo = apiErrors.titulo?.[0] ?? ''
      errors.cancelacion_horas_minimas = apiErrors.cancelacion_horas_minimas?.[0] ?? ''
    }
    ui.toast.error(e.response?.data?.message ?? 'No se pudo guardar.')
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="max-w-2xl">
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-neutral-900">Configuración</h1>
      <p class="text-neutral-500 mt-1 text-sm">Perfil profesional y políticas de cancelación.</p>
    </div>

    <div v-if="loading" class="flex justify-center py-16">
      <AppSpinner size="lg" />
    </div>

    <p v-else-if="error" class="text-red-600 text-sm">{{ error }}</p>

    <AppCard v-else>
      <form class="space-y-5" @submit.prevent="guardar">
        <AppInput
          id="titulo"
          v-model="form.titulo"
          label="Título profesional"
          placeholder="Ej: Nutricionista deportiva"
          required
          :error="errors.titulo"
        />

        <div class="flex flex-col gap-1">
          <label for="descripcion" class="text-sm font-medium text-neutral-700">Descripción</label>
          <textarea
            id="descripcion"
            v-model="form.descripcion"
            rows="5"
            placeholder="Contá tu experiencia, especialidades y enfoque..."
            class="w-full px-3 py-2 text-sm rounded-lg border border-neutral-300 bg-white outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 placeholder:text-neutral-400 resize-y"
          />
          <p class="text-xs text-neutral-400">Se muestra en tu perfil público.</p>
        </div>

        <AppInput
          id="cancelacion"
          v-model="form.cancelacion_horas_minimas"
          label="Horas mínimas para cancelar"
          type="number"
          hint="Los clientes deberán cancelar con al menos estas horas de anticipación. 0 = sin restricción."
          :error="errors.cancelacion_horas_minimas"
        />

        <div class="flex justify-end pt-2">
          <AppButton type="submit" :loading="saving">Guardar cambios</AppButton>
        </div>
      </form>
    </AppCard>
  </div>
</template>
