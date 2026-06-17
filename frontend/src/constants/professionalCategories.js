/** Categorías de profesionales (deben coincidir con backend/config/professional_categories.php). */
export const PROFESSIONAL_CATEGORIES = [
  { value: 'salud', label: 'Salud', emoji: '🩺' },
  { value: 'entrenamiento', label: 'Entrenamiento', emoji: '🏋️' },
  { value: 'educacion', label: 'Educación', emoji: '📚' },
  { value: 'consultoria', label: 'Consultoría', emoji: '💼' },
  { value: 'tecnologia', label: 'Tecnología', emoji: '💻' },
  { value: 'legal', label: 'Legal', emoji: '⚖️' },
  { value: 'finanzas', label: 'Finanzas', emoji: '📊' },
  { value: 'nutricion', label: 'Nutrición', emoji: '🥗' },
]

export function categoryLabel(value) {
  return PROFESSIONAL_CATEGORIES.find((c) => c.value === value)?.label ?? value
}
