<script setup>
import { onMounted, onBeforeUnmount, ref, watch } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

// Vite no resuelve por defecto los iconos del marker. Importamos las URLs
// explícitamente y se las inyectamos al Icon.Default.
import iconUrl from 'leaflet/dist/images/marker-icon.png'
import iconRetinaUrl from 'leaflet/dist/images/marker-icon-2x.png'
import shadowUrl from 'leaflet/dist/images/marker-shadow.png'

delete L.Icon.Default.prototype._getIconUrl
L.Icon.Default.mergeOptions({ iconUrl, iconRetinaUrl, shadowUrl })

const props = defineProps({
  latitud: { type: Number, required: true },
  longitud: { type: Number, required: true },
  titulo: { type: String, default: '' },
  zoom: { type: Number, default: 14 },
  alto: { type: String, default: '300px' },
})

const contenedor = ref(null)
let mapa = null
let marcador = null

function montarMapa() {
  if (!contenedor.value || mapa) return

  mapa = L.map(contenedor.value, {
    center: [props.latitud, props.longitud],
    zoom: props.zoom,
    scrollWheelZoom: false,
  })

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution:
      '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    maxZoom: 19,
  }).addTo(mapa)

  marcador = L.marker([props.latitud, props.longitud]).addTo(mapa)
  if (props.titulo) marcador.bindPopup(props.titulo).openPopup()
}

onMounted(montarMapa)

onBeforeUnmount(() => {
  if (mapa) {
    mapa.remove()
    mapa = null
    marcador = null
  }
})

// Si lat/lng cambian (por ejemplo, otro profesional en la misma vista),
// recentramos sin reconstruir el mapa.
watch(
  () => [props.latitud, props.longitud],
  ([lat, lng]) => {
    if (!mapa) return
    const punto = [lat, lng]
    mapa.setView(punto, props.zoom)
    if (marcador) marcador.setLatLng(punto)
  },
)
</script>

<template>
  <div
    ref="contenedor"
    :style="{ height: alto }"
    class="w-full rounded-xl overflow-hidden border border-neutral-200 z-0"
  ></div>
</template>
