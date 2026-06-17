<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { Search, MapPin, Star, Clock, Video, Users, Shield, Zap, ArrowRight, CheckCircle } from '@lucide/vue'
import api from '@/services/api'
import AppButton from '@/components/ui/AppButton.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppAvatar from '@/components/ui/AppAvatar.vue'
import AppSpinner from '@/components/ui/AppSpinner.vue'
import { PROFESSIONAL_CATEGORIES } from '@/constants/professionalCategories'

const router = useRouter()
const search = ref('')

const totalProfessionals = ref(0)
const cityList = ref([])
const featured = ref([])
const loadingHome = ref(true)
const categoryCounts = ref({})

const categories = PROFESSIONAL_CATEGORIES.map((cat) => ({
  name: cat.label,
  emoji: cat.emoji,
  value: cat.value,
}))

const steps = [
  {
    number: '01',
    title: 'Explorá profesionales',
    description: 'Buscá por especialidad, modalidad, precio y zona. Leé reseñas de otros clientes.',
    icon: Search,
  },
  {
    number: '02',
    title: 'Elegí un horario',
    description: 'Consultá la disponibilidad en tiempo real y reservá el turno que mejor te venga.',
    icon: Clock,
  },
  {
    number: '03',
    title: 'Confirmá y pagá',
    description: 'Pagá de forma segura con tarjeta o PayPal. Recibís confirmación al instante.',
    icon: CheckCircle,
  },
]

const features = [
  { icon: Video, title: 'Videollamadas integradas', description: 'Sesiones remotas sin salir de la plataforma.' },
  { icon: Shield, title: 'Pagos seguros', description: 'Integración con PayPal y tarjetas de crédito.' },
  { icon: Users, title: 'Paquetes de sesiones', description: 'Comprá múltiples sesiones con descuento.' },
  { icon: Zap, title: 'Recordatorios automáticos', description: 'Te avisamos antes de cada turno por email.' },
]

function fullName(pro) {
  return `${pro.nombre} ${pro.apellido}`.trim()
}

function modalidadLabel(m) {
  return { virtual: 'Virtual', presencial: 'Presencial', hibrida: 'Híbrida' }[m] ?? m
}

function modalidadesTexto(pro) {
  if (!pro.modalidades?.length) return '—'
  return pro.modalidades.map(modalidadLabel).join(' · ')
}

function formatPrice(n) {
  if (n == null) return '—'
  return new Intl.NumberFormat('es-UY', { style: 'currency', currency: 'UYU', maximumFractionDigits: 0 }).format(n)
}

function handleSearch() {
  const term = search.value.trim()
  router.push({
    name: 'professionals',
    query: term ? { search: term } : {},
  })
}

function goCategory(cat) {
  router.push({ name: 'professionals', query: { categoria: cat.value } })
}

function countForCategory(value) {
  return categoryCounts.value[value] ?? 0
}

async function loadHomeData() {
  loadingHome.value = true
  try {
    const [statsRes, featuredRes, allRes] = await Promise.all([
      api.get('/professionals', { params: { per_page: 1 } }),
      api.get('/professionals', { params: { sort: 'rating', per_page: 3 } }),
      api.get('/professionals', { params: { per_page: 50 } }),
    ])

    totalProfessionals.value = statsRes.data.meta?.total ?? 0
    featured.value = featuredRes.data.data ?? []

    const all = allRes.data.data ?? []
    const cities = [...new Set(all.map((p) => p.ubicacion?.ciudad).filter(Boolean))]
    cityList.value = cities.slice(0, 4)

    const counts = {}
    for (const cat of categories) {
      counts[cat.value] = all.filter((p) => p.categoria === cat.value).length
    }
    categoryCounts.value = counts
  } catch {
    featured.value = []
    totalProfessionals.value = 0
  } finally {
    loadingHome.value = false
  }
}

onMounted(loadHomeData)
</script>

<template>
  <div>

    <!-- ── Hero ──────────────────────────────────────────── -->
    <section class="relative bg-gradient-to-br from-primary-600 via-primary-700 to-primary-900 text-white overflow-hidden">
      <div class="absolute inset-0 opacity-10">
        <div class="absolute top-10 left-10 w-72 h-72 bg-white rounded-full blur-3xl" />
        <div class="absolute bottom-0 right-0 w-96 h-96 bg-accent-400 rounded-full blur-3xl" />
      </div>

      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-28">
        <div class="max-w-3xl">
          <span class="inline-flex items-center gap-2 px-3 py-1.5 bg-white/10 rounded-full text-sm font-medium text-white mb-6">
            <span class="w-2 h-2 bg-accent-400 rounded-full animate-pulse" />
            Plataforma de servicios profesionales
          </span>

          <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold leading-tight text-balance mb-6 text-white">
            Conectá con los mejores <span class="text-accent-300">profesionales</span>
          </h1>

          <p class="text-lg text-primary-100 mb-10 max-w-xl">
            Reservá turnos, agendá sesiones y gestioná tus servicios. Todo en un solo lugar, presencial o remoto.
          </p>

          <div class="flex flex-col sm:flex-row gap-3 bg-white rounded-2xl p-2 max-w-xl shadow-lg">
            <div class="flex-1 flex items-center gap-2 px-3">
              <Search class="w-5 h-5 text-neutral-400 shrink-0" />
              <input
                v-model="search"
                type="text"
                placeholder="¿Qué profesional buscás? (nombre, especialidad...)"
                class="flex-1 text-neutral-900 text-sm outline-none placeholder:text-neutral-400 bg-transparent"
                @keydown.enter="handleSearch"
              />
            </div>
            <AppButton variant="primary" size="lg" @click="handleSearch">
              Buscar
            </AppButton>
          </div>

          <p class="mt-4 text-sm text-primary-200">
            <template v-if="totalProfessionals > 0">
              {{ totalProfessionals }} profesional{{ totalProfessionals !== 1 ? 'es' : '' }} disponibles
              <template v-if="cityList.length">
                · {{ cityList.join(', ') }}{{ cityList.length >= 4 ? ' y más' : '' }}
              </template>
            </template>
            <template v-else-if="!loadingHome">
              Explorá profesionales en Uruguay
            </template>
          </p>
        </div>
      </div>
    </section>

    <!-- ── Categorías ──────────────────────────────────── -->
    <section class="py-16 bg-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-10">
          <h2 class="text-2xl sm:text-3xl font-bold text-neutral-900 mb-2">Explorá por categoría</h2>
          <p class="text-neutral-500">Encontrá el profesional ideal según tu necesidad</p>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3">
          <button
            v-for="cat in categories"
            :key="cat.name"
            class="flex flex-col items-center gap-2 p-4 rounded-xl border border-neutral-200 hover:border-primary-300 hover:bg-primary-50 transition-colors cursor-pointer group"
            @click="goCategory(cat)"
          >
            <span class="text-2xl group-hover:scale-110 transition-transform">{{ cat.emoji }}</span>
            <span class="text-xs font-medium text-neutral-700 text-center">{{ cat.name }}</span>
            <span class="text-xs text-neutral-400">
              {{ countForCategory(cat.value) || 'Ver' }}
            </span>
          </button>
        </div>
      </div>
    </section>

    <!-- ── Profesionales destacados ───────────────────── -->
    <section class="py-16 bg-neutral-50">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-10">
          <div>
            <h2 class="text-2xl sm:text-3xl font-bold text-neutral-900 mb-1">Profesionales destacados</h2>
            <p class="text-neutral-500">Los más valorados por nuestros clientes</p>
          </div>
          <AppButton variant="outline" size="sm" as="RouterLink" to="/professionals">
            Ver todos <ArrowRight class="w-4 h-4" />
          </AppButton>
        </div>

        <div v-if="loadingHome" class="flex justify-center py-16">
          <AppSpinner size="lg" />
        </div>

        <p v-else-if="featured.length === 0" class="text-center text-neutral-500 py-12">
          Todavía no hay profesionales publicados.
          <RouterLink to="/professionals" class="text-primary-600 hover:underline ml-1">Ver listado</RouterLink>
        </p>

        <div v-else class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <AppCard
            v-for="pro in featured"
            :key="pro.id"
            :hover="true"
            padding="none"
            as="div"
          >
            <div class="h-24 bg-gradient-to-br from-primary-500 to-primary-700 rounded-t-xl" />

            <div class="px-6 pb-6">
              <div class="-mt-8 mb-4">
                <AppAvatar :name="fullName(pro)" :src="pro.foto_perfil" size="xl" />
              </div>

              <div class="flex items-start justify-between gap-2 mb-2">
                <div>
                  <h3 class="font-semibold text-neutral-900">{{ fullName(pro) }}</h3>
                  <p class="text-sm text-neutral-500">{{ pro.titulo }}</p>
                </div>
                <div v-if="pro.rating_count > 0" class="flex items-center gap-1 shrink-0">
                  <Star class="w-4 h-4 text-amber-400 fill-amber-400" />
                  <span class="text-sm font-semibold text-neutral-900">{{ pro.rating_avg }}</span>
                  <span class="text-xs text-neutral-400">({{ pro.rating_count }})</span>
                </div>
              </div>

              <div v-if="pro.ubicacion" class="flex items-center gap-1 text-xs text-neutral-500 mb-3">
                <MapPin class="w-3.5 h-3.5" />
                {{ pro.ubicacion.ciudad }}, {{ pro.ubicacion.pais }}
                <span v-if="pro.modalidades?.length"> · {{ modalidadesTexto(pro) }}</span>
              </div>
              <p v-else class="text-xs text-neutral-500 mb-3">{{ modalidadesTexto(pro) }}</p>

              <div class="flex flex-wrap gap-1 mb-4">
                <AppBadge
                  v-for="m in pro.modalidades"
                  :key="m"
                  variant="primary"
                  size="sm"
                >
                  {{ modalidadLabel(m) }}
                </AppBadge>
              </div>

              <div class="flex items-center justify-between">
                <div>
                  <span class="text-xs text-neutral-400">Desde</span>
                  <p class="font-bold text-neutral-900">
                    {{ formatPrice(pro.precio_desde) }}
                    <span v-if="pro.precio_desde" class="text-xs font-normal text-neutral-500">/sesión</span>
                  </p>
                </div>
                <AppButton
                  variant="primary"
                  size="sm"
                  as="RouterLink"
                  :to="`/professionals/${pro.id}`"
                >
                  Ver perfil
                </AppButton>
              </div>
            </div>
          </AppCard>
        </div>
      </div>
    </section>

    <!-- ── Cómo funciona ───────────────────────────────── -->
    <section id="how-it-works" class="py-16 bg-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
          <h2 class="text-2xl sm:text-3xl font-bold text-neutral-900 mb-2">¿Cómo funciona?</h2>
          <p class="text-neutral-500">Reservá un turno en menos de 3 minutos</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
          <div v-for="step in steps" :key="step.number" class="flex flex-col items-center text-center">
            <div class="w-14 h-14 rounded-2xl bg-primary-50 flex items-center justify-center mb-4">
              <component :is="step.icon" class="w-6 h-6 text-primary-600" />
            </div>
            <span class="text-xs font-bold text-primary-400 mb-1 tracking-widest">PASO {{ step.number }}</span>
            <h3 class="font-semibold text-neutral-900 mb-2">{{ step.title }}</h3>
            <p class="text-sm text-neutral-500 leading-relaxed">{{ step.description }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ── Features ───────────────────────────────────── -->
    <section class="py-16 bg-gradient-to-br from-primary-700 via-primary-800 to-primary-900">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
          <h2 class="text-2xl sm:text-3xl font-bold text-white mb-2">Todo lo que necesitás</h2>
          <p class="text-primary-100 text-base">Una plataforma completa para profesionales y clientes</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
          <div
            v-for="feat in features"
            :key="feat.title"
            class="rounded-2xl p-6 border border-white/15 bg-white/10 hover:bg-white/15 transition-colors"
          >
            <div class="w-10 h-10 rounded-xl bg-white/15 flex items-center justify-center mb-4">
              <component :is="feat.icon" class="w-5 h-5 text-accent-200" />
            </div>
            <h3 class="font-semibold text-white mb-2">{{ feat.title }}</h3>
            <p class="text-sm text-primary-100 leading-relaxed">{{ feat.description }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ── CTA ────────────────────────────────────────── -->
    <section class="py-16 bg-white">
      <div class="max-w-2xl mx-auto px-4 text-center">
        <h2 class="text-2xl sm:text-3xl font-bold text-neutral-900 mb-4">
          ¿Sos profesional? <span class="text-primary-600">Empezá hoy</span>
        </h2>
        <p class="text-neutral-500 mb-8">
          Publicá tus servicios, configurá tu agenda y empezá a recibir clientes. Sin comisiones ocultas.
        </p>
        <div class="flex flex-col sm:flex-row gap-3 justify-center">
          <AppButton variant="primary" size="lg" as="RouterLink" to="/auth/register?role=professional">
            Registrarme como profesional
          </AppButton>
          <AppButton variant="outline" size="lg" as="RouterLink" to="/professionals">
            Explorar la plataforma
          </AppButton>
        </div>
      </div>
    </section>

  </div>
</template>
