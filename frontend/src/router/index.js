import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

import PublicLayout from '@/layouts/PublicLayout.vue'
import AuthLayout from '@/layouts/AuthLayout.vue'
import DashboardLayout from '@/layouts/DashboardLayout.vue'

const placeholder = () => import('@/pages/dashboard/DashboardPlaceholderPage.vue')

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  scrollBehavior: (to, from, saved) => saved ?? { top: 0 },
  routes: [
    // ── Público ──────────────────────────────────────────
    {
      path: '/',
      component: PublicLayout,
      children: [
        {
          path: '',
          name: 'home',
          component: () => import('@/pages/HomePage.vue'),
        },
        {
          path: 'professionals',
          name: 'professionals',
          component: () => import('@/pages/professionals/ProfessionalsPage.vue'),
        },
        {
          path: 'professionals/:id',
          name: 'professional-detail',
          component: () => import('@/pages/professionals/ProfessionalDetailPage.vue'),
        },
        {
          path: 'book/:professionalId',
          name: 'booking',
          component: () => import('@/pages/bookings/BookingPage.vue'),
          meta: { requiresAuth: true },
        },
        {
          path: 'pay/:bookingId/:paymentId',
          name: 'payment',
          component: () => import('@/pages/payments/PaymentPage.vue'),
          meta: { requiresAuth: true },
        },
        {
          path: 'pay/package/:purchaseId/:paymentId',
          name: 'payment-package',
          component: () => import('@/pages/payments/PaymentPage.vue'),
          meta: { requiresAuth: true },
        },
        {
          path: 'call/:bookingId',
          name: 'video-call',
          component: () => import('@/pages/call/VideoCallPage.vue'),
          meta: { requiresAuth: true },
        },
      ],
    },

    // ── Auth ─────────────────────────────────────────────
    {
      path: '/auth',
      component: AuthLayout,
      children: [
        {
          path: 'login',
          name: 'login',
          meta: { guestOnly: true },
          component: () => import('@/pages/auth/LoginPage.vue'),
        },
        {
          path: 'register',
          name: 'register',
          meta: { guestOnly: true },
          component: () => import('@/pages/auth/RegisterPage.vue'),
        },
        {
          path: 'oauth-callback',
          name: 'oauth-callback',
          component: () => import('@/pages/auth/OAuthCallbackPage.vue'),
        },
      ],
    },

    // ── Dashboard cliente ─────────────────────────────────
    {
      path: '/dashboard/client',
      component: DashboardLayout,
      meta: { requiresAuth: true, role: 'client' },
      children: [
        {
          path: '',
          name: 'client-dashboard',
          component: () => import('@/pages/dashboard/client/ClientDashboardPage.vue'),
        },
        {
          path: 'bookings',
          name: 'client-bookings',
          component: () => import('@/pages/dashboard/client/ClientBookingsPage.vue'),
        },
        {
          path: 'packages',
          name: 'client-packages',
          component: () => import('@/pages/dashboard/client/ClientPackagesPage.vue'),
        },
        {
          path: 'reviews',
          name: 'client-reviews',
          component: () => import('@/pages/dashboard/client/ClientReviewsPage.vue'),
          meta: { pageTitle: 'Mis reseñas', pageDescription: 'Calificaciones que escribiste.' },
        },
        {
          path: 'payments',
          name: 'client-payments',
          component: () => import('@/pages/dashboard/client/ClientPaymentsPage.vue'),
        },
      ],
    },

    // ── Dashboard profesional ─────────────────────────────
    {
      path: '/dashboard/professional',
      component: DashboardLayout,
      meta: { requiresAuth: true, role: 'professional' },
      children: [
        {
          path: '',
          name: 'professional-dashboard',
          component: () => import('@/pages/dashboard/professional/ProfessionalDashboardPage.vue'),
        },
        {
          path: 'schedule',
          name: 'professional-schedule',
          component: () =>
            import('@/pages/dashboard/professional/ProfessionalSchedulePage.vue'),
          meta: {
            pageTitle: 'Agenda',
            pageDescription: 'Horarios, buffer, pausas y excepciones (feriados).',
          },
        },
        {
          path: 'bookings',
          name: 'professional-bookings',
          component: () => import('@/pages/dashboard/professional/ProfessionalBookingsPage.vue'),
          meta: { pageTitle: 'Reservas', pageDescription: 'Turnos de tus clientes.' },
        },
        {
          path: 'clients',
          name: 'professional-clients',
          component: () =>
            import('@/pages/dashboard/professional/ProfessionalClientsPage.vue'),
        },
        {
          path: 'services',
          name: 'professional-services',
          component: () =>
            import('@/pages/dashboard/professional/ProfessionalServicesPage.vue'),
          meta: { pageTitle: 'Servicios', pageDescription: 'Sesiones y precios que ofrecés.' },
        },
        {
          path: 'packages',
          name: 'professional-packages',
          component: () =>
            import('@/pages/dashboard/professional/ProfessionalPackagesPage.vue'),
        },
        {
          path: 'reviews',
          name: 'professional-reviews',
          component: () => import('@/pages/dashboard/professional/ProfessionalReviewsPage.vue'),
          meta: { pageTitle: 'Reseñas', pageDescription: 'Calificaciones de tus clientes.' },
        },
        {
          path: 'settings',
          name: 'professional-settings',
          component: () => import('@/pages/dashboard/professional/ProfessionalSettingsPage.vue'),
        },
      ],
    },

    // ── Perfil (cualquier rol autenticado) ────────────────
    {
      path: '/profile',
      component: DashboardLayout,
      meta: { requiresAuth: true },
      children: [
        {
          path: '',
          name: 'profile',
          component: () => import('@/pages/profile/ProfilePage.vue'),
        },
      ],
    },

    // ── Notificaciones (cualquier rol autenticado) ────────
    {
      path: '/dashboard/notifications',
      component: DashboardLayout,
      meta: { requiresAuth: true },
      children: [
        {
          path: '',
          name: 'notifications',
          component: () => import('@/pages/dashboard/NotificationsPage.vue'),
        },
      ],
    },

    // ── Admin ─────────────────────────────────────────────
    {
      path: '/admin',
      component: DashboardLayout,
      meta: { requiresAuth: true, role: 'admin' },
      children: [
        {
          path: '',
          name: 'admin',
          component: () => import('@/pages/admin/AdminPage.vue'),
        },
        {
          path: 'users',
          name: 'admin-users',
          component: () => import('@/pages/admin/AdminUsersPage.vue'),
        },
        {
          path: 'bookings',
          name: 'admin-bookings',
          component: () => import('@/pages/admin/AdminBookingsPage.vue'),
          meta: { pageTitle: 'Reservas', pageDescription: 'Todas las reservas de la plataforma.' },
        },
        {
          path: 'settings',
          name: 'admin-settings',
          component: () => import('@/pages/admin/AdminSettingsPage.vue'),
          meta: { pageTitle: 'Configuración', pageDescription: 'Ajustes del sistema.' },
        },
      ],
    },

    // ── 404 ───────────────────────────────────────────────
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: () => import('@/pages/NotFoundPage.vue'),
    },
  ],
})

router.beforeEach((to, from) => {
  const auth = useAuthStore()

  if (to.meta.requiresAuth && !auth.isLoggedIn) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.meta.guestOnly && auth.isLoggedIn) {
    if (auth.role === 'professional') return { name: 'professional-dashboard' }
    if (auth.role === 'admin') return { name: 'admin' }
    return { name: 'client-dashboard' }
  }

  if (to.meta.role && auth.role !== to.meta.role) {
    return { name: 'home' }
  }
})

// Tras un deploy nuevo, los chunks lazy (.js con hash) viejos dejan de existir
// en el server. Si una navegación falla al cargar su módulo, recargamos la
// página hacia el destino para traer la versión actual — automatiza el
// "refrescar a mano" que había que hacer. El flag evita un bucle de recargas.
const FLAG_RECARGA_CHUNK = 'recarga-chunk-viejo'

router.onError((error, to) => {
  const mensaje = String(error?.message || '')
  const falloDeChunk = /dynamically imported module|Importing a module script failed|error loading dynamically imported module/i.test(mensaje)
  if (!falloDeChunk) return
  if (sessionStorage.getItem(FLAG_RECARGA_CHUNK)) return
  sessionStorage.setItem(FLAG_RECARGA_CHUNK, '1')
  window.location.assign(to?.fullPath || window.location.href)
})

// Navegación exitosa: limpiamos el flag para permitir futuras recargas.
router.afterEach(() => {
  sessionStorage.removeItem(FLAG_RECARGA_CHUNK)
})

export default router
