<script setup>
import { ref, reactive, onMounted } from 'vue'
import { Trash2, Save, Plus, Clock, CalendarX } from '@lucide/vue'
import api from '@/services/api'
import AppCard from '@/components/ui/AppCard.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppSpinner from '@/components/ui/AppSpinner.vue'

// dayOfWeek de Carbon: 0=Domingo, 1=Lunes, ..., 6=Sábado.
const DIAS = [
  { value: 1, label: 'Lun' },
  { value: 2, label: 'Mar' },
  { value: 3, label: 'Mié' },
  { value: 4, label: 'Jue' },
  { value: 5, label: 'Vie' },
  { value: 6, label: 'Sáb' },
  { value: 0, label: 'Dom' },
]

const cargando = ref(true)
const guardando = ref(false)
const agregandoExcepcion = ref(false)
const error = ref(null)
const mensajeOk = ref(null)

const formulario = reactive({
  horario_inicio: '09:00',
  horario_fin: '18:00',
  dias_disponibles: [1, 2, 3, 4, 5],
  buffer_minutos: 15,
})
const erroresAgenda = ref({})

const excepciones = ref([])
const nuevaExcepcion = reactive({ fecha: '', motivo: '' })
const erroresExcepcion = ref({})

async function cargar() {
  cargando.value = true
  error.value = null
  try {
    const { data } = await api.get('/professional/agenda')
    if (data.agenda) {
      formulario.horario_inicio = data.agenda.horario_inicio
      formulario.horario_fin = data.agenda.horario_fin
      formulario.dias_disponibles = [...data.agenda.dias_disponibles]
      formulario.buffer_minutos = data.agenda.buffer_minutos
      excepciones.value = data.agenda.exceptions ?? []
    }
  } catch (e) {
    error.value = e.response?.data?.message ?? 'No se pudo cargar la agenda.'
  } finally {
    cargando.value = false
  }
}

function alternarDia(dia) {
  const idx = formulario.dias_disponibles.indexOf(dia)
  if (idx >= 0) formulario.dias_disponibles.splice(idx, 1)
  else formulario.dias_disponibles.push(dia)
}

function diaActivo(dia) {
  return formulario.dias_disponibles.includes(dia)
}

async function guardar() {
  guardando.value = true
  erroresAgenda.value = {}
  mensajeOk.value = null
  try {
    await api.put('/professional/agenda', {
      horario_inicio: formulario.horario_inicio,
      horario_fin: formulario.horario_fin,
      dias_disponibles: formulario.dias_disponibles,
      buffer_minutos: Number(formulario.buffer_minutos),
    })
    mensajeOk.value = 'Agenda guardada.'
    setTimeout(() => (mensajeOk.value = null), 3000)
  } catch (e) {
    if (e.response?.status === 422) {
      erroresAgenda.value = e.response.data.errors ?? {}
    } else {
      error.value = e.response?.data?.message ?? 'No se pudo guardar la agenda.'
    }
  } finally {
    guardando.value = false
  }
}

async function agregarExcepcion() {
  agregandoExcepcion.value = true
  erroresExcepcion.value = {}
  try {
    const { data } = await api.post('/professional/agenda/exceptions', {
      fecha: nuevaExcepcion.fecha,
      motivo: nuevaExcepcion.motivo,
    })
    excepciones.value.push(data)
    excepciones.value.sort((a, b) => a.fecha.localeCompare(b.fecha))
    nuevaExcepcion.fecha = ''
    nuevaExcepcion.motivo = ''
  } catch (e) {
    if (e.response?.status === 422) {
      erroresExcepcion.value = e.response.data.errors ?? {}
    } else {
      error.value = e.response?.data?.message ?? 'No se pudo agregar la excepción.'
    }
  } finally {
    agregandoExcepcion.value = false
  }
}

async function eliminarExcepcion(id) {
  try {
    await api.delete(`/professional/agenda/exceptions/${id}`)
    excepciones.value = excepciones.value.filter((e) => e.id !== id)
  } catch (e) {
    error.value = e.response?.data?.message ?? 'No se pudo eliminar la excepción.'
  }
}

function formatearFecha(iso) {
  const [a, m, d] = iso.split('-')
  return `${d}/${m}/${a}`
}

onMounted(cargar)
</script>

<template>
  <div class="max-w-3xl">
    <header class="mb-6">
      <h1 class="text-2xl font-bold text-neutral-900">Agenda</h1>
      <p class="text-sm text-neutral-500 mt-1">
        Definí tus horarios habituales y los días que no atendés.
      </p>
    </header>

    <div v-if="cargando" class="flex justify-center py-12">
      <AppSpinner size="lg" />
    </div>

    <template v-else>
      <div
        v-if="error"
        class="mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-sm text-red-700"
      >
        {{ error }}
      </div>
      <div
        v-if="mensajeOk"
        class="mb-4 px-4 py-3 rounded-lg bg-green-50 border border-green-200 text-sm text-green-700"
      >
        {{ mensajeOk }}
      </div>

      <AppCard class="mb-6" padding="md">
        <h2 class="font-semibold text-neutral-900 mb-4 flex items-center gap-2">
          <Clock class="w-5 h-5 text-neutral-500" /> Horario semanal
        </h2>

        <div class="grid sm:grid-cols-2 gap-4 mb-4">
          <AppInput
            v-model="formulario.horario_inicio"
            type="time"
            label="Hora de inicio"
            :error="erroresAgenda.horario_inicio?.[0]"
            required
          />
          <AppInput
            v-model="formulario.horario_fin"
            type="time"
            label="Hora de fin"
            :error="erroresAgenda.horario_fin?.[0]"
            required
          />
        </div>

        <div class="mb-4">
          <label class="text-sm font-medium text-neutral-700 mb-2 block">
            Días disponibles <span class="text-red-500">*</span>
          </label>
          <div class="flex flex-wrap gap-2">
            <button
              v-for="dia in DIAS"
              :key="dia.value"
              type="button"
              @click="alternarDia(dia.value)"
              :class="[
                'px-3 py-1.5 text-sm rounded-lg border transition-colors cursor-pointer',
                diaActivo(dia.value)
                  ? 'bg-primary-600 text-white border-primary-600'
                  : 'bg-white text-neutral-700 border-neutral-300 hover:bg-neutral-50',
              ]"
            >
              {{ dia.label }}
            </button>
          </div>
          <p
            v-if="erroresAgenda.dias_disponibles?.[0]"
            class="text-xs text-red-600 mt-1"
          >
            {{ erroresAgenda.dias_disponibles[0] }}
          </p>
        </div>

        <AppInput
          v-model="formulario.buffer_minutos"
          type="number"
          label="Buffer entre reservas (minutos)"
          hint="Tiempo de descanso entre turnos consecutivos."
          :error="erroresAgenda.buffer_minutos?.[0]"
          required
        />

        <div class="mt-5 flex justify-end">
          <AppButton variant="primary" :loading="guardando" @click="guardar">
            <Save class="w-4 h-4" /> Guardar agenda
          </AppButton>
        </div>
      </AppCard>

      <AppCard padding="md">
        <h2 class="font-semibold text-neutral-900 mb-4 flex items-center gap-2">
          <CalendarX class="w-5 h-5 text-neutral-500" /> Excepciones
        </h2>
        <p class="text-sm text-neutral-500 mb-4">
          Días puntuales en los que no vas a atender (feriados, licencias, etc.).
        </p>

        <div v-if="!excepciones.length" class="text-sm text-neutral-400 italic mb-4">
          Sin excepciones cargadas.
        </div>
        <ul v-else class="divide-y divide-neutral-200 mb-5">
          <li
            v-for="exc in excepciones"
            :key="exc.id"
            class="flex items-center justify-between py-3"
          >
            <div>
              <p class="text-sm font-medium text-neutral-900">
                {{ formatearFecha(exc.fecha) }}
              </p>
              <p class="text-xs text-neutral-500">{{ exc.motivo }}</p>
            </div>
            <button
              type="button"
              @click="eliminarExcepcion(exc.id)"
              class="p-2 text-neutral-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors cursor-pointer"
              :title="`Eliminar excepción del ${formatearFecha(exc.fecha)}`"
            >
              <Trash2 class="w-4 h-4" />
            </button>
          </li>
        </ul>

        <div class="grid sm:grid-cols-[160px_1fr_auto] gap-3 items-end">
          <AppInput
            v-model="nuevaExcepcion.fecha"
            type="date"
            label="Fecha"
            :error="erroresExcepcion.fecha?.[0]"
          />
          <AppInput
            v-model="nuevaExcepcion.motivo"
            label="Motivo"
            placeholder="Feriado, viaje, etc."
            :error="erroresExcepcion.motivo?.[0]"
          />
          <AppButton
            variant="secondary"
            :loading="agregandoExcepcion"
            :disabled="!nuevaExcepcion.fecha || !nuevaExcepcion.motivo"
            @click="agregarExcepcion"
          >
            <Plus class="w-4 h-4" /> Agregar
          </AppButton>
        </div>
      </AppCard>
    </template>
  </div>
</template>
