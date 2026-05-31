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
          component: placeholder,
          meta: {
            pageTitle: 'Mis paquetes',
            pageDescription: 'Paquetes de sesiones comprados y sesiones restantes.',
          },
        },
        {
          path: 'reviews',
          name: 'client-reviews',
          component: placeholder,
          meta: { pageTitle: 'Mis reseñas', pageDescription: 'Calificaciones que escribiste.' },
        },
        {
          path: 'payments',
          name: 'client-payments',
          component: placeholder,
          meta: { pageTitle: 'Pagos', pageDescription: 'Historial de pagos de reservas y paquetes.' },
        },
        {
          path: 'settings',
          name: 'client-settings',
          component: placeholder,
          meta: { pageTitle: 'Configuración', pageDescription: 'Preferencias de tu cuenta.' },
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
          component: placeholder,
          meta: {
            pageTitle: 'Agenda',
            pageDescription: 'Horarios, buffers y excepciones (feriados).',
          },
        },
        {
          path: 'clients',
          name: 'professional-clients',
          component: placeholder,
          meta: { pageTitle: 'Clientes', pageDescription: 'Personas que reservaron con vos.' },
        },
        {
          path: 'services',
          name: 'professional-services',
          component: placeholder,
          meta: { pageTitle: 'Servicios', pageDescription: 'Sesiones y precios que ofrecés.' },
        },
        {
          path: 'packages',
          name: 'professional-packages',
          component: placeholder,
          meta: { pageTitle: 'Paquetes', pageDescription: 'Paquetes de múltiples sesiones.' },
        },
        {
          path: 'reviews',
          name: 'professional-reviews',
          component: placeholder,
          meta: { pageTitle: 'Reseñas', pageDescription: 'Calificaciones de tus clientes.' },
        },
        {
          path: 'metrics',
          name: 'professional-metrics',
          component: placeholder,
          meta: { pageTitle: 'Métricas', pageDescription: 'Resumen de actividad y ingresos.' },
        },
        {
          path: 'settings',
          name: 'professional-settings',
          component: placeholder,
          meta: { pageTitle: 'Configuración', pageDescription: 'Perfil profesional y políticas.' },
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
          component: placeholder,
          meta: { pageTitle: 'Usuarios', pageDescription: 'Gestión de clientes y profesionales.' },
        },
        {
          path: 'professionals',
          name: 'admin-professionals',
          component: placeholder,
          meta: { pageTitle: 'Profesionales', pageDescription: 'Moderación de perfiles profesionales.' },
        },
        {
          path: 'metrics',
          name: 'admin-metrics',
          component: placeholder,
          meta: { pageTitle: 'Métricas', pageDescription: 'Uso general de la plataforma.' },
        },
        {
          path: 'settings',
          name: 'admin-settings',
          component: placeholder,
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

export default router
