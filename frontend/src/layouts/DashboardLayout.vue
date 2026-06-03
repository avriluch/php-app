<script setup>
import { Menu } from '@lucide/vue'
import AppSidebar from '@/components/layout/AppSidebar.vue'
import AppAvatar from '@/components/ui/AppAvatar.vue'
import NotificationsBell from '@/components/layout/NotificationsBell.vue'
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
          <NotificationsBell />

          <!-- Avatar / nombre -->
          <RouterLink to="/profile" class="flex items-center gap-2 no-underline">
            <AppAvatar :src="auth.user?.foto_perfil" :name="auth.displayName" size="sm" />
            <span class="hidden sm:block text-sm font-medium text-neutral-700">{{ auth.displayName }}</span>
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
