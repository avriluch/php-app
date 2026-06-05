/**
 * Convierte rutas relativas del storage de Laravel en URL absoluta del backend.
 */
function apiOrigin() {
  const apiBase = (import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api').replace(/\/$/, '')
  return apiBase.replace(/\/api$/, '')
}

export function resolveMediaUrl(src) {
  if (!src) return null

  let path = String(src).trim()

  if (path.startsWith('blob:') || path.startsWith('data:')) {
    return path
  }

  // Normaliza rutas guardadas en BD: /storage/avatars/...
  if (path.includes('/storage/')) {
    path = path.slice(path.indexOf('/storage/'))
  }

  if (path.startsWith('http://') || path.startsWith('https://')) {
    return path
  }

  const origin = apiOrigin()
  const relative = path.startsWith('/') ? path : `/${path}`

  return `${origin}${relative}`
}
