<script setup>
import { ref, computed, watch } from 'vue'
import { ChevronLeft, ChevronRight } from '@lucide/vue'

const props = defineProps({
  /** Fecha seleccionada en formato 'YYYY-MM-DD'. */
  modelValue: { type: String, default: '' },
  /** Fecha mínima seleccionable en formato 'YYYY-MM-DD' (opcional). */
  min: { type: String, default: '' },
})
const emit = defineEmits(['update:modelValue'])

const MESES = [
  'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
  'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre',
]
// Semana arrancando en lunes (convención local).
const DIAS = ['Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sá', 'Do']

/** Convierte una fecha local a 'YYYY-MM-DD' sin pasar por UTC (evita off-by-one). */
function aISO(anio, mes, dia) {
  const mm = String(mes + 1).padStart(2, '0')
  const dd = String(dia).padStart(2, '0')
  return `${anio}-${mm}-${dd}`
}

function partes(iso) {
  if (!iso) return null
  const [a, m, d] = iso.split('-').map(Number)
  if (!a || !m || !d) return null
  return { anio: a, mes: m - 1, dia: d }
}

const hoy = new Date()
const inicial = partes(props.modelValue) ?? partes(props.min) ?? { anio: hoy.getFullYear(), mes: hoy.getMonth() }

// Mes que se está mostrando (año + mes).
const anioVista = ref(inicial.anio)
const mesVista = ref(inicial.mes)

// Si cambia el valor desde afuera, reposicionar la vista en ese mes.
watch(() => props.modelValue, (val) => {
  const p = partes(val)
  if (p) {
    anioVista.value = p.anio
    mesVista.value = p.mes
  }
})

const tituloMes = computed(() => `${MESES[mesVista.value]} ${anioVista.value}`)

const celdas = computed(() => {
  const primerDia = new Date(anioVista.value, mesVista.value, 1)
  // getDay(): 0=Dom..6=Sáb. Lo pasamos a 0=Lun..6=Dom.
  const offset = (primerDia.getDay() + 6) % 7
  const diasEnMes = new Date(anioVista.value, mesVista.value + 1, 0).getDate()

  const lista = []
  for (let i = 0; i < offset; i++) lista.push(null)
  for (let d = 1; d <= diasEnMes; d++) {
    const iso = aISO(anioVista.value, mesVista.value, d)
    lista.push({
      dia: d,
      iso,
      deshabilitado: props.min ? iso < props.min : false,
      seleccionado: iso === props.modelValue,
      esHoy: iso === aISO(hoy.getFullYear(), hoy.getMonth(), hoy.getDate()),
    })
  }
  return lista
})

// No dejar retroceder a meses anteriores al mínimo permitido.
const puedeRetroceder = computed(() => {
  if (!props.min) return true
  const p = partes(props.min)
  return anioVista.value > p.anio || (anioVista.value === p.anio && mesVista.value > p.mes)
})

function mesAnterior() {
  if (!puedeRetroceder.value) return
  if (mesVista.value === 0) {
    mesVista.value = 11
    anioVista.value--
  } else {
    mesVista.value--
  }
}

function mesSiguiente() {
  if (mesVista.value === 11) {
    mesVista.value = 0
    anioVista.value++
  } else {
    mesVista.value++
  }
}

function seleccionar(celda) {
  if (!celda || celda.deshabilitado) return
  emit('update:modelValue', celda.iso)
}
</script>

<template>
  <div class="select-none">
    <!-- Encabezado: mes + navegación -->
    <div class="flex items-center justify-between mb-3">
      <button
        type="button"
        :disabled="!puedeRetroceder"
        class="w-8 h-8 flex items-center justify-center rounded-lg text-neutral-500 hover:bg-neutral-100 hover:text-neutral-900 transition-colors cursor-pointer disabled:opacity-30 disabled:cursor-not-allowed"
        @click="mesAnterior"
      >
        <ChevronLeft class="w-5 h-5" />
      </button>
      <span class="text-sm font-semibold text-neutral-900 capitalize">{{ tituloMes }}</span>
      <button
        type="button"
        class="w-8 h-8 flex items-center justify-center rounded-lg text-neutral-500 hover:bg-neutral-100 hover:text-neutral-900 transition-colors cursor-pointer"
        @click="mesSiguiente"
      >
        <ChevronRight class="w-5 h-5" />
      </button>
    </div>

    <!-- Días de la semana -->
    <div class="grid grid-cols-7 gap-1 mb-1">
      <span
        v-for="d in DIAS"
        :key="d"
        class="h-8 flex items-center justify-center text-xs font-medium text-neutral-400"
      >
        {{ d }}
      </span>
    </div>

    <!-- Grilla de días -->
    <div class="grid grid-cols-7 gap-1">
      <template v-for="(celda, i) in celdas" :key="i">
        <span v-if="!celda" class="h-9"></span>
        <button
          v-else
          type="button"
          :disabled="celda.deshabilitado"
          :class="[
            'h-9 flex items-center justify-center rounded-lg text-sm font-medium transition-colors',
            celda.deshabilitado
              ? 'text-neutral-300 cursor-not-allowed'
              : celda.seleccionado
                ? 'bg-primary-600 text-white cursor-pointer'
                : 'text-neutral-700 hover:bg-primary-50 hover:text-primary-700 cursor-pointer',
            !celda.seleccionado && celda.esHoy && !celda.deshabilitado
              ? 'ring-1 ring-inset ring-primary-300'
              : '',
          ]"
          @click="seleccionar(celda)"
        >
          {{ celda.dia }}
        </button>
      </template>
    </div>
  </div>
</template>
