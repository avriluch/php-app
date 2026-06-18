# Estado actual del proyecto

Snapshot técnico al **26 de mayo 2026**. Qué endpoints existen, qué conecta el frontend, qué falta. Pensado como punto de partida para chats nuevos.

---

## 1. Servicios que deben estar corriendo

| Servicio | Puerto | Cómo arrancar |
|----------|--------|---------------|
| MariaDB (XAMPP) | 3307 | XAMPP Control Panel → Start MySQL |
| Redis | 6379 | `redis-server` |
| Laravel API | 8000 | `php artisan serve` desde `backend/` |
| Vite dev | 5173 | `npm run dev` desde `frontend/` |
| Queue worker | — | `php artisan queue:work redis` |
| Reverb WebSocket | 8080 | `php artisan reverb:start` |

Branch principal de trabajo: **`feature/backend-mvp`** (pusheada a `origin`).

---

## 2. Endpoints implementados (todos respondiendo OK)

### Públicos
```
GET    /api/health
POST   /api/auth/register
POST   /api/auth/login
GET    /api/auth/google/redirect
GET    /api/auth/google/callback
GET    /api/professionals                    (filtros: search, modalidad, type, precio_min, precio_max, rating_min, ciudad, pais, sort=rating|price)
GET    /api/professionals/{id}
GET    /api/professionals/{id}/services
GET    /api/professionals/{id}/availability  (service_id, from, to)
GET    /api/professionals/{id}/reviews
```

### Autenticados (Sanctum)
```
GET    /api/auth/me
PATCH  /api/auth/me
POST   /api/auth/logout
POST   /api/broadcasting/auth           (autenticación de canales privados Reverb)

GET    /api/me/stats                    (forma distinta por rol)

GET    /api/bookings                    (auto-filtrado por rol)
POST   /api/bookings                    (concurrencia + validaciones)
GET    /api/bookings/{id}
PATCH  /api/bookings/{id}/cancel
PATCH  /api/bookings/{id}/reschedule
PATCH  /api/bookings/{id}/status        (solo professional|admin)
POST   /api/bookings/{id}/review

POST   /api/payments/{id}/paypal/create-order   (cliente)
POST   /api/payments/{id}/paypal/capture        (cliente)

GET    /api/reviews/mine                (cliente)

GET    /api/notifications
PATCH  /api/notifications/{id}/read
PATCH  /api/notifications/read-all

GET    /api/package-purchases           (cliente)
POST   /api/package-purchases           (cliente)
```

### Solo profesional
```
GET    /api/professional/agenda
PUT    /api/professional/agenda
POST   /api/professional/agenda/exceptions
DELETE /api/professional/agenda/exceptions/{id}

GET    /api/professional/services
POST   /api/professional/services
PATCH  /api/professional/services/{id}
DELETE /api/professional/services/{id}
```

### Solo admin
```
GET    /api/admin/users
GET    /api/admin/metrics
```

---

## 3. Endpoints **conectados al frontend** (lo que se ve en pantalla hoy)

| Página Vue | Endpoint(s) que consume |
|------------|-------------------------|
| `/auth/login`, `/auth/register` | `POST /auth/login` · `POST /auth/register` |
| `/profile` | `GET /auth/me` (solo lectura) |
| `/professionals` | `GET /professionals` (con filtros) |
| `/professionals/:id` | `GET /professionals/{id}` |
| `/book/:professionalId` | `GET /professionals/{id}` · `/services` · `/availability` · `POST /bookings` |
| `/dashboard/client/bookings` | `GET /bookings` |
| `/payments/:bookingId` | `GET /bookings/{id}` · `POST /payments/{id}/paypal/create-order` · `POST /payments/{id}/paypal/capture` |

### Páginas del frontend que existen pero todavía son **placeholder** (no consumen API)
- Editar perfil
- Stats de los 3 dashboards (cards muestran `—`)
- Agenda del profesional
- CRUD servicios del profesional
- Lista/compra de paquetes
- Centro de notificaciones
- Crear/ver reseñas
- Admin (users, professionals, metrics)

---

## 4. Infraestructura asíncrona implementada

### Cola Redis + Worker
- Driver: `predis` (no `phpredis`, evita necesidad de extensión PHP).
- Conexión `default` (db 0) para cola, `cache` (db 1) para cache.
- Worker corre con `php artisan queue:work redis`.

### Jobs
| Job | Disparado desde | Hace |
|-----|-----------------|------|
| `EnviarConfirmacionReserva` | `BookingController::store` | Crea 2 `Notification` (cliente + profesional), manda 2 emails, emite Event WebSocket al profesional |
| `EnviarCancelacionReserva` | `BookingController::cancel` | Crea 2 `Notification`, manda 2 emails |
| `EnviarRecordatorioReserva` | Command `bookings:enviar-recordatorios` | Crea 1 `Notification` recordatorio, manda 1 email al cliente |

### Mailables (vistas Markdown en `resources/views/mail/`)
- `ReservaCreadaMail` (con destinatario cliente|profesional)
- `ReservaCanceladaMail` (idem)
- `ReservaRecordatorioMail`

### Scheduler
```
Schedule::command('bookings:enviar-recordatorios')->hourly()->withoutOverlapping();
```
En desarrollo se ejecuta manualmente; en producción correr `php artisan schedule:work` o configurar cron.

### Driver de mail
`MAIL_MAILER=log` — los emails se escriben en `storage/logs/laravel.log` con HTML completo.

---

## 5. WebSockets (Reverb)

- Servidor en :8080 (`php artisan reverb:start`).
- Cliente frontend en `frontend/src/services/echo.js` con `laravel-echo` + `pusher-js`.
- Canal privado: `private-profesional.{user_id}`.
- Autenticación: `POST /api/broadcasting/auth` con Bearer token Sanctum.
- Event: `App\Events\NuevaReservaProfesional` implementa `ShouldBroadcastNow` y se emite cuando se crea una reserva (desde el Job `EnviarConfirmacionReserva`).

---

## 6. Reglas de validación clave

### Reservas (`BookingController::store`)
- Solo clientes pueden crear.
- `fecha_hora` debe ser futura (`after:now`).
- Modalidad de reserva debe coincidir con la del servicio, o el servicio debe ser híbrido.
- Si `service.type=package`, `package_purchase_id` es obligatorio.
- `package_purchase` debe pertenecer al cliente, mismo servicio, y tener `sesiones_restantes > 0`.
- Slot libre verificado con `AvailabilityService::isSlotFree`.

### Cancelación
- Solo si la transición es válida (`BookingStatus::canTransitionTo(Cancelada)`).
- Si quien cancela es el cliente, valida `cancelacion_horas_minimas` del profesional.
- Si la reserva consumía un paquete, restaura `sesiones_restantes`.

### Reseñas (`ReviewController::store`)
- Solo cliente dueño del booking.
- Solo si `booking.estado = finalizada`.
- Una sola reseña por booking.

### Servicios (`ServiceController::store|update`)
- `duracion` obligatoria si `type=session`.
- `cantidad_sesiones` obligatoria si `type=package`.
- `location_id` obligatorio si modalidad ≠ virtual.

### Transiciones de estado de reserva
```
pendiente  → confirmada | cancelada
confirmada → pagada | cancelada
pagada     → en_curso | cancelada
en_curso   → finalizada | no_asistida
```
Implementado en `BookingStatus::allowedTransitions()` y `canTransitionTo()`.

---

## 7. Cumplimiento de la entrega académica

### Obligatorios
| Requisito | Estado |
|-----------|--------|
| Responsive | ✅ (Tailwind) |
| API REST | ✅ |
| Auth + roles | ✅ Sanctum + middleware `role:` |
| Email | ✅ Mailables + cola |
| Mapa | ⚠️ backend devuelve lat/lng, frontend pendiente |
| Reseñas | ✅ |
| WebSockets | ✅ Reverb |
| Videollamada | ❌ pendiente LiveKit |

### Electivos (puntos)
| Item | Pts | Estado |
|------|-----|--------|
| Arq desacoplada | 4 | ✅ |
| OAuth Google | 2 | ✅ |
| Concurrencia | 3 | ✅ |
| Colas async | 3 | ✅ |
| Recordatorios | 2 | ✅ |
| PayPal sandbox | 2 | ✅ |
| **Confirmados** | **16** | |
| PWA | 3 | ⏳ frontend |
| Nube + HTTPS | 2 | ⏳ |
| **Objetivo final** | **21** | |

Mínimo para grupo de 4: **20 puntos**. Estamos a **4 puntos** del mínimo, con dos electivos pendientes que totalizan 5 puntos.

---

## 8. Pendientes priorizados

1. **LiveKit tokens** (obligatorio videollamada) — endpoint que firma JWT efímero al pasar a `en_curso` virtual, cliente JS de LiveKit en frontend.
2. **PWA en el frontend** (+3 pts) — manifest, Service Worker, ícono, offline parcial.
3. **Deploy a nube con HTTPS** (+2 pts) — Render/Railway/Fly para backend, Vercel/Netlify para frontend.
4. **Mapa con Leaflet** (obligatorio) en el frontend — usar `lat`/`lng` que ya devuelve `/professionals/{id}`.
5. **Conectar páginas placeholder al backend** — agenda, paquetes, servicios CRUD, notificaciones, admin, edición de perfil.
6. **Switch de driver mail a Mailtrap** para que los emails se vean en la defensa.

---

## 9. Archivos clave por si hay que tocar algo rápido

### Backend
| Qué | Archivo |
|-----|---------|
| Rutas API | `backend/routes/api.php` |
| Canales WS | `backend/routes/channels.php` |
| Scheduler | `backend/routes/console.php` |
| Modelos | `backend/app/Models/*.php` |
| Enums | `backend/app/Enums/*.php` |
| Controllers | `backend/app/Http/Controllers/Api/*.php` |
| Jobs async | `backend/app/Jobs/*.php` |
| Mailables | `backend/app/Mail/*.php` |
| Events WS | `backend/app/Events/*.php` |
| Lógica de disponibilidad | `backend/app/Services/AvailabilityService.php` |
| Configuración mail/cola/redis | `backend/config/*.php` |
| Migraciones | `backend/database/migrations/*.php` |
| Seeder demo | `backend/database/seeders/DatabaseSeeder.php` |
| Plantillas email | `backend/resources/views/mail/*.blade.php` |

### Frontend
| Qué | Archivo |
|-----|---------|
| Cliente axios | `frontend/src/services/api.js` |
| Cliente Echo (WS) | `frontend/src/services/echo.js` |
| Store de auth | `frontend/src/stores/auth.js` |
| Router con guards | `frontend/src/router/index.js` |
| Páginas | `frontend/src/pages/**/*.vue` |

---

## 10. Usuarios demo (seeder)

| Email | Rol | Password |
|-------|-----|----------|
| `admin@demo.test` | admin | `password` |
| `cliente@demo.test` | client | `password` |
| `profesional@demo.test` | professional | `password` |

Seeder crea además:
- 1 location en Montevideo (Carlos Pérez)
- 1 professional_profile (Carlos, Nutricionista, cancelación 24h)
- 1 agenda (lun-vie 09:00–18:00, buffer 15min)
- 3 services: Consulta inicial (presencial $1500), Seguimiento virtual ($1200), Pack 8 sesiones ($10000)

Para resetear: `php artisan migrate:fresh --seed --force`.
