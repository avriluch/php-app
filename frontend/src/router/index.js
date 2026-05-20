import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

import PublicLayout from '@/layouts/PublicLayout.vue'
import AuthLayout from '@/layouts/AuthLayout.vue'
import DashboardLayout from '@/layouts/DashboardLayout.vue'

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
      ],
    },

    // ── Auth ─────────────────────────────────────────────
    {
      path: '/auth',
      component: AuthLayout,
      meta: { guestOnly: true },
      children: [
        {
          path: 'login',
          name: 'login',
          component: () => import('@/pages/auth/LoginPage.vue'),
        },
        {
          path: 'register',
          name: 'register',
          component: () => import('@/pages/auth/RegisterPage.vue'),
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
