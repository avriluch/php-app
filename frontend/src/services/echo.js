// Cliente WebSocket para recibir eventos en vivo del backend (Laravel Reverb).
//
// Uso típico en una página:
//
//   import echo from '@/services/echo'
//   import { useAuthStore } from '@/stores/auth'
//
//   const auth = useAuthStore()
//
//   onMounted(() => {
//     const canal = echo.private(`App.Models.User.${auth.user.id}`)
//     canal.listen('.nueva-reserva', (data) => {
//       console.log('Nueva reserva:', data)
//     })
//   })
//
//   onBeforeUnmount(() => {
//     echo.leave(`App.Models.User.${auth.user.id}`)
//   })
//
// Requiere: npm install laravel-echo pusher-js

import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

window.Pusher = Pusher

const echo = new Echo({
  broadcaster: 'reverb',
  key: import.meta.env.VITE_REVERB_APP_KEY,
  wsHost: import.meta.env.VITE_REVERB_HOST,
  wsPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
  wssPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
  forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
  enabledTransports: ['ws', 'wss'],

  // Autenticación del canal privado vía Sanctum.
  // El endpoint /api/broadcasting/auth lee el Bearer token del header.
  authEndpoint: `${import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api'}/broadcasting/auth`,
  auth: {
    headers: {
      get Authorization() {
        const token = localStorage.getItem('token')
        return token ? `Bearer ${token}` : ''
      },
      Accept: 'application/json',
    },
  },
})

export default echo
