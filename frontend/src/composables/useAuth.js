import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'

export function useAuth() {
  const auth = useAuthStore()
  const router = useRouter()

  const requireAuth = (redirectTo = '/auth/login') => {
    if (!auth.isLoggedIn) router.push(redirectTo)
  }

  const requireRole = (role, redirectTo = '/') => {
    if (auth.role !== role) router.push(redirectTo)
  }

  const redirectToDashboard = () => {
    if (auth.role === 'professional') router.push('/dashboard/professional')
    else if (auth.role === 'admin') router.push('/admin')
    else router.push('/dashboard/client')
  }

  return { auth, requireAuth, requireRole, redirectToDashboard }
}
