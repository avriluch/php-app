<script setup>
import { Menu, Bell } from '@lucide/vue'
import AppSidebar from '@/components/layout/AppSidebar.vue'
import AppAvatar from '@/components/ui/AppAvatar.vue'
import { useUIStore } from '@/stores/ui'
import { useAuthStore } from '@/stores/auth'
import { RouterLink } from 'vue-router'

const ui = useUIStore()
const auth = useAuthStore()
</script>

<template>
  <div class="flex h-screen bg-neutral-50 overflow-hidden">
    <AppSidebar />

    <div class="flex-1 flex flex-col overflow-hidden">
      <!-- Topbar -->
      <header class="h-16 bg-white border-b border-neutral-200 flex items-center justify-between px-4 sm:px-6 shrink-0">
        <button
          class="p-2 rounded-lg hover:bg-neutral-100 text-neutral-600 cursor-pointer"
          @click="ui.sidebarOpen = !ui.sidebarOpen"
        >
          <Menu class="w-5 h-5" />
        </button>

        <div class="flex items-center gap-3">
          <!-- Notificaciones -->
          <button class="relative p-2 rounded-lg hover:bg-neutral-100 text-neutral-600 cursor-pointer">
            <Bell class="w-5 h-5" />
            <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full" />
          </button>

          <!-- Avatar / nombre -->
          <RouterLink to="/profile" class="flex items-center gap-2 no-underline">
            <AppAvatar :name="auth.user?.name" size="sm" />
            <span class="hidden sm:block text-sm font-medium text-neutral-700">{{ auth.user?.name }}</span>
          </RouterLink>
        </div>
      </header>

      <!-- Contenido -->
      <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
        <RouterView />
      </main>
    </div>
  </div>
</template>
