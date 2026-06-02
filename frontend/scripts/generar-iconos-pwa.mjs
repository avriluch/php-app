// Genera los íconos PNG necesarios para el manifest PWA a partir del SVG fuente.
// Correr con: node scripts/generar-iconos-pwa.mjs

import { readFileSync, writeFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, resolve } from 'node:path'
import sharp from 'sharp'

const __dirname = dirname(fileURLToPath(import.meta.url))
const PUBLIC_DIR = resolve(__dirname, '..', 'public')
const FUENTE = resolve(PUBLIC_DIR, 'icon-source.svg')

const svgFuente = readFileSync(FUENTE)

// Versión "maskable": agranda el ícono dentro de un fondo lleno para respetar
// la safe-zone del 80% que recomienda el spec PWA.
const svgMaskable = Buffer.from(`
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
  <rect width="512" height="512" fill="#4f46e5"/>
  <g transform="translate(64 64) scale(0.75)">
    ${svgFuente.toString().replace(/<\?xml[^>]*\?>/, '').replace(/<svg[^>]*>/, '').replace('</svg>', '')}
  </g>
</svg>
`)

const objetivos = [
  { archivo: 'pwa-192.png', tamano: 192, fuente: svgFuente },
  { archivo: 'pwa-512.png', tamano: 512, fuente: svgFuente },
  { archivo: 'pwa-maskable-512.png', tamano: 512, fuente: svgMaskable },
  { archivo: 'apple-touch-icon.png', tamano: 180, fuente: svgFuente },
  { archivo: 'favicon-32.png', tamano: 32, fuente: svgFuente },
]

for (const { archivo, tamano, fuente } of objetivos) {
  const destino = resolve(PUBLIC_DIR, archivo)
  await sharp(fuente, { density: 384 })
    .resize(tamano, tamano, { fit: 'contain', background: { r: 0, g: 0, b: 0, alpha: 0 } })
    .png({ compressionLevel: 9 })
    .toFile(destino)
  console.log(`✓ ${archivo} (${tamano}x${tamano})`)
}

// También copiamos el SVG con un nombre fijo para que index.html lo use como favicon vectorial.
writeFileSync(resolve(PUBLIC_DIR, 'favicon.svg'), svgFuente)
console.log('✓ favicon.svg')
