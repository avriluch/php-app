<script setup>
import { onMounted, onBeforeUnmount, ref, watch, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

import iconUrl from 'leaflet/dist/images/marker-icon.png'
import iconRetinaUrl from 'leaflet/dist/images/marker-icon-2x.png'
import shadowUrl from 'leaflet/dist/images/marker-shadow.png'

delete L.Icon.Default.prototype._getIconUrl
L.Icon.Default.mergeOptions({ iconUrl, iconRetinaUrl, shadowUrl })

const props = defineProps({
  professionals: { type: Array, default: () => [] },
  userLat: { type: Number, default: null },
  userLng: { type: Number, default: null },
  radiusKm: { type: Number, default: null },
  alto: { type: String, default: '360px' },
})

const emit = defineEmits(['grouped-count'])

const router = useRouter()
const contenedor = ref(null)

let mapa = null
let capaMarcadores = null
let marcadorUsuario = null
let circuloRadio = null

function nombreProfesional(pro) {
  return `${pro.nombre ?? ''} ${pro.apellido ?? ''}`.trim() || 'Profesional'
}

function formatoDistancia(km) {
  if (km == null) return ''
  return km < 1 ? `${Math.round(km * 1000)} m` : `${km} km`
}

function escapeHtml(text) {
  return String(text)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
}

/** Agrupa profesionales que comparten las mismas coordenadas (misma ciudad en BD). */
function agruparPorCoordenadas(lista) {
  const grupos = new Map()

  lista.forEach((pro) => {
    const ub = pro.ubicacion
    if (ub?.latitud == null || ub?.longitud == null) return

    const lat = Number(ub.latitud)
    const lng = Number(ub.longitud)
    const key = `${lat.toFixed(5)},${lng.toFixed(5)}`

    if (!grupos.has(key)) {
      grupos.set(key, { lat, lng, ciudad: ub.ciudad, pais: ub.pais, pros: [] })
    }
    grupos.get(key).pros.push(pro)
  })

  return [...grupos.values()]
}

/** Reparte marcadores en círculo cuando varios comparten ubicación exacta. */
function posicionEnGrupo(lat, lng, index, total) {
  if (total <= 1) return [lat, lng]

  const radioM = Math.min(180, 50 + total * 12)
  const angulo = (2 * Math.PI * index) / total - Math.PI / 2
  const dLat = (radioM / 111320) * Math.cos(angulo)
  const dLng = (radioM / (111320 * Math.cos((lat * Math.PI) / 180))) * Math.sin(angulo)

  return [lat + dLat, lng + dLng]
}

function popupGrupo(grupo) {
  const ciudad = grupo.ciudad
    ? escapeHtml(`${grupo.ciudad}${grupo.pais ? `, ${grupo.pais}` : ''}`)
    : ''

  const items = grupo.pros
    .map((pro) => {
      const nombre = escapeHtml(nombreProfesional(pro))
      const titulo = escapeHtml(pro.titulo ?? '')
      const dist = formatoDistancia(pro.distance_km)
      return `
        <button type="button" class="prof-map-link block w-full text-left py-2 border-b border-neutral-100 last:border-0 hover:bg-neutral-50 rounded px-1 -mx-1" data-id="${pro.id}">
          <span class="font-medium text-neutral-900 text-sm">${nombre}</span>
          ${titulo ? `<span class="block text-xs text-primary-600">${titulo}</span>` : ''}
          ${dist ? `<span class="block text-xs text-neutral-500">${dist}</span>` : ''}
        </button>
      `
    })
    .join('')

  return `
    <div class="min-w-[200px] max-h-52 overflow-y-auto">
      <p class="font-semibold text-neutral-900 text-sm mb-1">${grupo.pros.length} profesionales</p>
      ${ciudad ? `<p class="text-xs text-neutral-500 mb-2">${ciudad}</p>` : ''}
      ${items}
    </div>
  `
}

function popupIndividual(pro) {
  const ub = pro.ubicacion
  const nombre = escapeHtml(nombreProfesional(pro))
  const titulo = escapeHtml(pro.titulo ?? '')
  const ciudad = ub?.ciudad ? escapeHtml(`${ub.ciudad}, ${ub.pais ?? ''}`) : ''
  const dist = formatoDistancia(pro.distance_km)
  const id = pro.id

  return `
    <div class="min-w-[160px]">
      <p class="font-semibold text-neutral-900 leading-tight">${nombre}</p>
      ${titulo ? `<p class="text-sm text-primary-600 mt-0.5">${titulo}</p>` : ''}
      ${ciudad ? `<p class="text-xs text-neutral-500 mt-1">${ciudad}</p>` : ''}
      ${dist ? `<p class="text-xs text-primary-600 font-medium mt-1">${dist}</p>` : ''}
      <button type="button" class="prof-map-link mt-2 text-sm font-medium text-primary-600 hover:underline cursor-pointer" data-id="${id}">
        Ver perfil →
      </button>
    </div>
  `
}

function enlazarPopup(marcador, id) {
  marcador.on('popupopen', () => {
    const popup = marcador.getPopup()?.getElement()
    if (!popup) return
    popup.querySelectorAll('.prof-map-link').forEach((btn) => {
      if (btn.dataset.bound) return
      btn.dataset.bound = '1'
      btn.addEventListener('click', () => {
        const proId = btn.dataset.id
        if (proId) router.push(`/professionals/${proId}`)
      })
    })
  })
}

function iconoGrupo(cantidad) {
  return L.divIcon({
    className: '',
    html: `<div class="flex items-center justify-center w-9 h-9 rounded-full bg-primary-600 text-white text-sm font-bold border-2 border-white shadow-md">${cantidad}</div>`,
    iconSize: [36, 36],
    iconAnchor: [18, 18],
  })
}

function montarMapa() {
  if (!contenedor.value || mapa) return

  const centro = props.userLat != null && props.userLng != null
    ? [props.userLat, props.userLng]
    : [-34.9011, -56.1645]

  mapa = L.map(contenedor.value, {
    center: centro,
    zoom: 12,
    scrollWheelZoom: true,
  })

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution:
      '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    maxZoom: 19,
  }).addTo(mapa)

  capaMarcadores = L.layerGroup().addTo(mapa)
  actualizarMarcadores()
}

function actualizarMarcadores() {
  if (!mapa || !capaMarcadores) return

  capaMarcadores.clearLayers()

  if (marcadorUsuario) {
    mapa.removeLayer(marcadorUsuario)
    marcadorUsuario = null
  }
  if (circuloRadio) {
    mapa.removeLayer(circuloRadio)
    circuloRadio = null
  }

  const puntos = []
  const grupos = agruparPorCoordenadas(props.professionals)
  const gruposMultiples = grupos.filter((g) => g.pros.length > 1).length
  emit('grouped-count', gruposMultiples)

  if (props.userLat != null && props.userLng != null) {
    const usuario = [props.userLat, props.userLng]
    puntos.push(usuario)

    marcadorUsuario = L.circleMarker(usuario, {
      radius: 9,
      color: '#2563eb',
      fillColor: '#3b82f6',
      fillOpacity: 0.9,
      weight: 2,
    })
      .addTo(mapa)
      .bindPopup('<strong>Tu ubicación</strong>')

    if (props.radiusKm != null && props.radiusKm > 0) {
      circuloRadio = L.circle(usuario, {
        radius: props.radiusKm * 1000,
        color: '#3b82f6',
        fillColor: '#3b82f6',
        fillOpacity: 0.08,
        weight: 1.5,
        dashArray: '6 4',
      }).addTo(mapa)
    }
  }

  grupos.forEach((grupo) => {
    const { lat, lng, pros } = grupo

    if (pros.length > 1) {
      const marcadorCentro = L.marker([lat, lng], { icon: iconoGrupo(pros.length) })
        .bindPopup(popupGrupo(grupo))
      enlazarPopup(marcadorCentro)
      capaMarcadores.addLayer(marcadorCentro)
      puntos.push([lat, lng])

      pros.forEach((pro, index) => {
        const coords = posicionEnGrupo(lat, lng, index, pros.length)
        puntos.push(coords)

        const marcador = L.marker(coords).bindPopup(popupIndividual(pro))
        enlazarPopup(marcador, pro.id)
        capaMarcadores.addLayer(marcador)

        L.polyline([[lat, lng], coords], {
          color: '#94a3b8',
          weight: 1,
          dashArray: '3 4',
          opacity: 0.7,
        }).addTo(capaMarcadores)
      })
    } else {
      const pro = pros[0]
      const coords = [lat, lng]
      puntos.push(coords)

      const marcador = L.marker(coords).bindPopup(popupIndividual(pro))
      enlazarPopup(marcador, pro.id)
      capaMarcadores.addLayer(marcador)
    }
  })

  if (puntos.length === 0 && props.userLat != null && props.userLng != null) {
    mapa.setView([props.userLat, props.userLng], props.radiusKm ? 11 : 13)
  } else if (puntos.length === 1) {
    mapa.setView(puntos[0], props.radiusKm ? 11 : 13)
  } else if (puntos.length > 1) {
    mapa.fitBounds(L.latLngBounds(puntos), { padding: [48, 48], maxZoom: 15 })
  }

  nextTick(() => mapa?.invalidateSize())
}

onMounted(() => {
  nextTick(montarMapa)
})

onBeforeUnmount(() => {
  if (mapa) {
    mapa.remove()
    mapa = null
    capaMarcadores = null
    marcadorUsuario = null
    circuloRadio = null
  }
})

watch(
  () => [props.professionals, props.userLat, props.userLng, props.radiusKm],
  () => actualizarMarcadores(),
  { deep: true },
)
</script>

<template>
  <div
    ref="contenedor"
    :style="{ height: alto }"
    class="w-full z-0"
  />
</template>
