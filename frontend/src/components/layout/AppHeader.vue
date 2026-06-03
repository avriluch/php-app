<script setup>
import { ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { Menu, X, ChevronDown, LogOut, LayoutDashboard, User } from '@lucide/vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppAvatar from '@/components/ui/AppAvatar.vue'
import NotificationsBell from '@/components/layout/NotificationsBell.vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const mobileOpen = ref(false)
const userMenuOpen = ref(false)

const navLinks = [
  { label: 'Inicio', to: '/' },
  { label: 'Profesionales', to: '/professionals' },
]

const dashboardRoute = () => {
  if (auth.role === 'professional') return '/dashboard/professional'
  if (auth.role === 'admin') return '/admin'
  return '/dashboard/client'
}

const handleLogout = () => {
  auth.logout()
  userMenuOpen.value = false
  router.push('/')
}
</script>

<template>
  <header class="sticky top-0 z-50 bg-white border-b border-neutral-200" style="box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.05)">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex items-center justify-between h-16">

        <!-- Logo -->
        <RouterLink to="/" class="flex items-center gap-2 text-primary-600 no-underline">
          <span class="w-8 h-8 bg-primary-600 rounded-lg flex items-center justify-center">
            <span class="text-white font-bold text-sm">S</span>
          </span>
          <span class="font-bold text-lg text-neutral-900">ServiConnect</span>
        </RouterLink>

        <!-- Nav desktop -->
        <nav class="hidden md:flex items-center gap-6">
          <RouterLink
            v-for="link in navLinks"
            :key="link.to"
            :to="link.to"
            class="text-sm font-medium text-neutral-600 hover:text-primary-600 transition-colors no-underline"
            active-class="text-primary-600"
          >
            {{ link.label }}
          </RouterLink>
        </nav>

        <!-- Auth / User -->
        <div class="hidden md:flex items-center gap-3">
          <template v-if="!auth.isLoggedIn">
            <AppButton variant="ghost" size="sm" as="RouterLink" to="/auth/login">
              Ingresar
            </AppButton>
            <AppButton variant="primary" size="sm" as="RouterLink" to="/auth/register">
              Registrarse
            </AppButton>
          </template>

          <template v-else>
            <NotificationsBell />

            <div class="relative">
              <button
                class="flex items-center gap-2 text-sm font-medium text-neutral-700 hover:text-neutral-900 transition-colors cursor-pointer"
                @click="userMenuOpen = !userMenuOpen"
              >
                <AppAvatar :src="auth.user?.foto_perfil" :name="auth.displayName" size="sm" />
                <span class="hidden lg:block">{{ auth.displayName }}</span>
                <ChevronDown class="w-4 h-4 text-neutral-400" />
              </button>

              <div
                v-if="userMenuOpen"
                class="absolute right-0 mt-2 w-48 bg-white rounded-xl border border-neutral-200 py-1 z-10"
                style="box-shadow: var(--shadow-modal)"
              >
                <RouterLink
                  :to="dashboardRoute()"
                  class="flex items-center gap-2 px-4 py-2 text-sm text-neutral-700 hover:bg-neutral-50 no-underline"
                  @click="userMenuOpen = false"
                >
                  <LayoutDashboard class="w-4 h-4" /> Mi panel
                </RouterLink>
                <RouterLink
                  to="/profile"
                  class="flex items-center gap-2 px-4 py-2 text-sm text-neutral-700 hover:bg-neutral-50 no-underline"
                  @click="userMenuOpen = false"
                >
                  <User class="w-4 h-4" /> Mi perfil
                </RouterLink>
                <hr class="my-1 border-neutral-100" />
                <button
                  class="w-full flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50 cursor-pointer"
                  @click="handleLogout"
                >
                  <LogOut class="w-4 h-4" /> Cerrar sesión
                </button>
              </div>
            </div>
          </template>
        </div>

        <!-- Mobile right cluster: bell + toggle -->
        <div class="flex items-center gap-1 md:hidden">
          <NotificationsBell v-if="auth.isLoggedIn" />
          <button
            class="p-2 rounded-lg text-neutral-600 hover:bg-neutral-100 cursor-pointer"
            @click="mobileOpen = !mobileOpen"
          >
            <Menu v-if="!mobileOpen" class="w-5 h-5" />
            <X v-else class="w-5 h-5" />
          </button>
        </div>
      </div>
    </div>

    <!-- Mobile menu -->
    <div v-if="mobileOpen" class="md:hidden border-t border-neutral-100 bg-white px-4 pb-4 pt-2">
      <nav class="flex flex-col gap-1">
        <RouterLink
          v-for="link in navLinks"
          :key="link.to"
          :to="link.to"
          class="text-sm font-medium text-neutral-700 hover:text-primary-600 py-2 no-underline"
          @click="mobileOpen = false"
        >
          {{ link.label }}
        </RouterLink>
      </nav>
      <div class="flex flex-col gap-2 mt-3 pt-3 border-t border-neutral-100">
        <template v-if="!auth.isLoggedIn">
          <AppButton variant="outline" size="sm" as="RouterLink" to="/auth/login" @click="mobileOpen = false">
            Ingresar
          </AppButton>
          <AppButton variant="primary" size="sm" as="RouterLink" to="/auth/register" @click="mobileOpen = false">
            Registrarse
          </AppButton>
        </template>
        <template v-else>
          <AppButton variant="ghost" size="sm" as="RouterLink" :to="dashboardRoute()" @click="mobileOpen = false">
            Mi panel
          </AppButton>
          <AppButton variant="outline" size="sm" @click="handleLogout">
            Cerrar sesión
          </AppButton>
        </template>
      </div>
    </div>
  </header>
</template>
