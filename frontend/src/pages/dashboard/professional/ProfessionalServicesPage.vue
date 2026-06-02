<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { Plus, Pencil, Power, X, Save, Briefcase } from '@lucide/vue'
import api from '@/services/api'
import AppCard from '@/components/ui/AppCard.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppSpinner from '@/components/ui/AppSpinner.vue'

const TIPOS = [
  { value: 'session', label: 'Sesión individual' },
  { value: 'package', label: 'Paquete' },
]
const MODALIDADES = [
  { value: 'virtual', label: 'Virtual' },
  { value: 'presencial', label: 'Presencial' },
  { value: 'hibrida', label: 'Híbrida' },
]
const VALOR_NUEVA_UBICACION = '__nueva__'

const cargando = ref(true)
const guardando = ref(false)
const error = ref(null)
const mensajeOk = ref(null)

const servicios = ref([])
const ubicaciones = ref([])
const editandoId = ref(null)
const mostrandoForm = ref(false)
const erroresForm = ref({})

const formularioVacio = () => ({
  type: 'session',
  nombre: '',
  descripcion: '',
  duracion: 60,
  cantidad_sesiones: 4,
  precio: 0,
  modalidad: 'virtual',
  location_id: '',
  activo: true,
  // Para crear ubicación on-the-fly:
  nuevaUbicacion: { ciudad: '', pais: 'UY', latitud: -34.901112, longitud: -56.164531 },
})
const formulario = reactive(formularioVacio())

const necesitaUbicacion = computed(() =>
  ['presencial', 'hibrida'].includes(formulario.modalidad),
)
const creandoUbicacion = computed(
  () => necesitaUbicacion.value && formulario.location_id === VALOR_NUEVA_UBICACION,
)
const serviciosActivos = computed(() => servicios.value.filter((s) => s.activo))
const serviciosInactivos = computed(() => servicios.value.filter((s) => !s.activo))

async function cargar() {
  cargando.value = true
  error.value = null
  try {
    const [resServ, resLoc] = await Promise.all([
      api.get('/professional/services'),
      api.get('/professional/locations'),
    ])
    servicios.value = resServ.data.data ?? []
    ubicaciones.value = resLoc.data.data ?? []
  } catch (e) {
    error.value = e.response?.data?.message ?? 'No se pudieron cargar los servicios.'
  } finally {
    cargando.value = false
  }
}

function abrirNuevo() {
  Object.assign(formulario, formularioVacio())
  editandoId.value = null
  erroresForm.value = {}
  mostrandoForm.value = true
}

function abrirEdicion(srv) {
  Object.assign(formulario, formularioVacio(), {
    type: srv.type,
    nombre: srv.nombre,
    descripcion: srv.descripcion ?? '',
    duracion: srv.duracion ?? 60,
    cantidad_sesiones: srv.cantidad_sesiones ?? 4,
    precio: srv.precio,
    modalidad: srv.modalidad,
    location_id: srv.location_id ?? '',
    activo: srv.activo,
  })
  editandoId.value = srv.id
  erroresForm.value = {}
  mostrandoForm.value = true
}

function cerrarForm() {
  mostrandoForm.value = false
  editandoId.value = null
  erroresForm.value = {}
}

async function crearUbicacionSiHaceFalta() {
  if (!creandoUbicacion.value) return formulario.location_id || null
  const { data } = await api.post('/professional/locations', {
    ciudad: formulario.nuevaUbicacion.ciudad,
    pais: formulario.nuevaUbicacion.pais.toUpperCase(),
    latitud: Number(formulario.nuevaUbicacion.latitud),
    longitud: Number(formulario.nuevaUbicacion.longitud),
  })
  ubicaciones.value.push(data)
  formulario.location_id = data.id
  return data.id
}

async function guardar() {
  guardando.value = true
  erroresForm.value = {}
  mensajeOk.value = null
  try {
    let locationId = null
    if (necesitaUbicacion.value) {
      locationId = await crearUbicacionSiHaceFalta()
    }

    const payload = {
      type: formulario.type,
      nombre: formulario.nombre,
      descripcion: formulario.descripcion || null,
      precio: Number(formulario.precio),
      modalidad: formulario.modalidad,
      location_id: necesitaUbicacion.value ? locationId : null,
      activo: formulario.activo,
    }
    if (formulario.type === 'session') {
      payload.duracion = Number(formulario.duracion)
      payload.cantidad_sesiones = null
    } else {
      payload.cantidad_sesiones = Number(formulario.cantidad_sesiones)
      payload.duracion = formulario.duracion ? Number(formulario.duracion) : null
    }

    if (editandoId.value) {
      const { data } = await api.patch(
        `/professional/services/${editandoId.value}`,
        payload,
      )
      const idx = servicios.value.findIndex((s) => s.id === editandoId.value)
      if (idx >= 0) servicios.value[idx] = data
      mensajeOk.value = 'Servicio actualizado.'
    } else {
      const { data } = await api.post('/professional/services', payload)
      servicios.value.unshift(data)
      mensajeOk.value = 'Servicio creado.'
    }
    cerrarForm()
    setTimeout(() => (mensajeOk.value = null), 3000)
  } catch (e) {
    if (e.response?.status === 422) {
      erroresForm.value = e.response.data.errors ?? {}
    } else {
      error.value = e.response?.data?.message ?? 'No se pudo guardar el servicio.'
    }
  } finally {
    guardando.value = false
  }
}

async function alternarActivo(srv) {
  try {
    if (srv.activo) {
      await api.delete(`/professional/services/${srv.id}`)
      srv.activo = false
    } else {
      const { data } = await api.patch(`/professional/services/${srv.id}`, {
        activo: true,
      })
      const idx = servicios.value.findIndex((s) => s.id === srv.id)
      if (idx >= 0) servicios.value[idx] = data
    }
  } catch (e) {
    error.value = e.response?.data?.message ?? 'No se pudo actualizar el servicio.'
  }
}

function precioFmt(v) {
  return new Intl.NumberFormat('es-UY', {
    style: 'currency',
    currency: 'UYU',
    maximumFractionDigits: 0,
  }).format(v)
}

function modalidadLabel(m) {
  return MODALIDADES.find((x) => x.value === m)?.label ?? m
}

onMounted(cargar)
</script>

<template>
  <div class="max-w-4xl">
    <header class="mb-6 flex items-start justify-between gap-4 flex-wrap">
      <div>
        <h1 class="text-2xl font-bold text-neutral-900">Servicios</h1>
        <p class="text-sm text-neutral-500 mt-1">
          Sesiones individuales y paquetes que ofrecés.
        </p>
      </div>
      <AppButton v-if="!mostrandoForm" variant="primary" @click="abrirNuevo">
        <Plus class="w-4 h-4" /> Nuevo servicio
      </AppButton>
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

      <AppCard v-if="mostrandoForm" class="mb-6" padding="md">
        <div class="flex items-center justify-between mb-4">
          <h2 class="font-semibold text-neutral-900 flex items-center gap-2">
            <Briefcase class="w-5 h-5 text-neutral-500" />
            {{ editandoId ? 'Editar servicio' : 'Nuevo servicio' }}
          </h2>
          <button
            type="button"
            class="p-2 text-neutral-400 hover:text-neutral-700 cursor-pointer"
            @click="cerrarForm"
          >
            <X class="w-4 h-4" />
          </button>
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-neutral-700">Tipo *</label>
            <select
              v-model="formulario.type"
              class="w-full px-3 py-2 text-sm rounded-lg border border-neutral-300 bg-white"
            >
              <option v-for="t in TIPOS" :key="t.value" :value="t.value">
                {{ t.label }}
              </option>
            </select>
            <p v-if="erroresForm.type?.[0]" class="text-xs text-red-600">
              {{ erroresForm.type[0] }}
            </p>
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-neutral-700">Modalidad *</label>
            <select
              v-model="formulario.modalidad"
              class="w-full px-3 py-2 text-sm rounded-lg border border-neutral-300 bg-white"
            >
              <option v-for="m in MODALIDADES" :key="m.value" :value="m.value">
                {{ m.label }}
              </option>
            </select>
            <p v-if="erroresForm.modalidad?.[0]" class="text-xs text-red-600">
              {{ erroresForm.modalidad[0] }}
            </p>
          </div>

          <div class="sm:col-span-2">
            <AppInput
              v-model="formulario.nombre"
              label="Nombre"
              placeholder="Ej: Consulta inicial"
              :error="erroresForm.nombre?.[0]"
              required
            />
          </div>

          <div class="sm:col-span-2">
            <label class="text-sm font-medium text-neutral-700 mb-1 block">
              Descripción
            </label>
            <textarea
              v-model="formulario.descripcion"
              rows="3"
              class="w-full px-3 py-2 text-sm rounded-lg border border-neutral-300 bg-white focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none"
              placeholder="Detalle del servicio (opcional)"
            ></textarea>
            <p v-if="erroresForm.descripcion?.[0]" class="text-xs text-red-600 mt-1">
              {{ erroresForm.descripcion[0] }}
            </p>
          </div>

          <AppInput
            v-if="formulario.type === 'session'"
            v-model="formulario.duracion"
            type="number"
            label="Duración (minutos)"
            :error="erroresForm.duracion?.[0]"
            required
          />
          <AppInput
            v-else
            v-model="formulario.cantidad_sesiones"
            type="number"
            label="Cantidad de sesiones"
            :error="erroresForm.cantidad_sesiones?.[0]"
            required
          />

          <AppInput
            v-model="formulario.precio"
            type="number"
            label="Precio (UYU)"
            :error="erroresForm.precio?.[0]"
            required
          />
        </div>

        <div v-if="necesitaUbicacion" class="mt-4">
          <label class="text-sm font-medium text-neutral-700 mb-1 block">
            Ubicación *
          </label>
          <select
            v-model="formulario.location_id"
            class="w-full px-3 py-2 text-sm rounded-lg border border-neutral-300 bg-white"
          >
            <option value="" disabled>Elegí una ubicación</option>
            <option v-for="u in ubicaciones" :key="u.id" :value="u.id">
              {{ u.ciudad }}, {{ u.pais }}
            </option>
            <option :value="VALOR_NUEVA_UBICACION">+ Nueva ubicación</option>
          </select>
          <p v-if="erroresForm.location_id?.[0]" class="text-xs text-red-600 mt-1">
            {{ erroresForm.location_id[0] }}
          </p>

          <div
            v-if="creandoUbicacion"
            class="mt-4 grid sm:grid-cols-2 gap-3 p-4 rounded-lg bg-neutral-50 border border-neutral-200"
          >
            <AppInput
              v-model="formulario.nuevaUbicacion.ciudad"
              label="Ciudad"
              placeholder="Montevideo"
              required
            />
            <AppInput
              v-model="formulario.nuevaUbicacion.pais"
              label="País (ISO 2)"
              placeholder="UY"
              required
            />
            <AppInput
              v-model="formulario.nuevaUbicacion.latitud"
              type="number"
              label="Latitud"
              required
            />
            <AppInput
              v-model="formulario.nuevaUbicacion.longitud"
              type="number"
              label="Longitud"
              required
            />
          </div>
        </div>

        <div class="mt-5 flex justify-end gap-2">
          <AppButton variant="outline" @click="cerrarForm">Cancelar</AppButton>
          <AppButton variant="primary" :loading="guardando" @click="guardar">
            <Save class="w-4 h-4" />
            {{ editandoId ? 'Guardar cambios' : 'Crear servicio' }}
          </AppButton>
        </div>
      </AppCard>

      <div v-if="!servicios.length && !mostrandoForm" class="py-12 text-center">
        <p class="text-neutral-500 mb-4">Todavía no publicaste servicios.</p>
        <AppButton variant="primary" @click="abrirNuevo">
          <Plus class="w-4 h-4" /> Crear el primero
        </AppButton>
      </div>

      <section v-if="serviciosActivos.length" class="mb-8">
        <h2 class="text-sm font-semibold text-neutral-500 uppercase tracking-wide mb-3">
          Activos
        </h2>
        <div class="grid gap-3">
          <AppCard
            v-for="srv in serviciosActivos"
            :key="srv.id"
            padding="md"
          >
            <div class="flex items-start justify-between gap-4 flex-wrap">
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <h3 class="font-semibold text-neutral-900">{{ srv.nombre }}</h3>
                  <AppBadge variant="primary" size="sm">
                    {{ srv.type === 'package' ? 'Paquete' : 'Sesión' }}
                  </AppBadge>
                  <AppBadge variant="default" size="sm">
                    {{ modalidadLabel(srv.modalidad) }}
                  </AppBadge>
                </div>
                <p v-if="srv.descripcion" class="text-sm text-neutral-500 mt-1">
                  {{ srv.descripcion }}
                </p>
                <p class="text-sm text-neutral-600 mt-2">
                  <span class="font-semibold text-primary-600">{{
                    precioFmt(srv.precio)
                  }}</span>
                  <span v-if="srv.duracion"> · {{ srv.duracion }} min</span>
                  <span v-if="srv.type === 'package'">
                    · {{ srv.cantidad_sesiones }} sesiones</span
                  >
                  <span v-if="srv.ubicacion">
                    · {{ srv.ubicacion.ciudad }}</span
                  >
                </p>
              </div>
              <div class="flex items-center gap-1 shrink-0">
                <button
                  type="button"
                  class="p-2 text-neutral-500 hover:text-primary-600 hover:bg-primary-50 rounded-lg cursor-pointer"
                  title="Editar"
                  @click="abrirEdicion(srv)"
                >
                  <Pencil class="w-4 h-4" />
                </button>
                <button
                  type="button"
                  class="p-2 text-neutral-500 hover:text-red-600 hover:bg-red-50 rounded-lg cursor-pointer"
                  title="Desactivar"
                  @click="alternarActivo(srv)"
                >
                  <Power class="w-4 h-4" />
                </button>
              </div>
            </div>
          </AppCard>
        </div>
      </section>

      <section v-if="serviciosInactivos.length">
        <h2 class="text-sm font-semibold text-neutral-500 uppercase tracking-wide mb-3">
          Desactivados
        </h2>
        <div class="grid gap-3">
          <AppCard
            v-for="srv in serviciosInactivos"
            :key="srv.id"
            padding="md"
            class="opacity-60"
          >
            <div class="flex items-start justify-between gap-4 flex-wrap">
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <h3 class="font-semibold text-neutral-900 line-through">
                    {{ srv.nombre }}
                  </h3>
                  <AppBadge variant="default" size="sm">Desactivado</AppBadge>
                </div>
                <p class="text-sm text-neutral-600 mt-2">
                  {{ precioFmt(srv.precio) }} ·
                  {{ modalidadLabel(srv.modalidad) }}
                </p>
              </div>
              <div class="flex items-center gap-1 shrink-0">
                <button
                  type="button"
                  class="p-2 text-neutral-500 hover:text-green-600 hover:bg-green-50 rounded-lg cursor-pointer"
                  title="Reactivar"
                  @click="alternarActivo(srv)"
                >
                  <Power class="w-4 h-4" />
                </button>
              </div>
            </div>
          </AppCard>
        </div>
      </section>
    </template>
  </div>
</template>
