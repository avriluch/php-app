# Contrato API v0 — Plataforma de servicios profesionales

Documento de acuerdo entre **frontend (Vue)** y **backend (Laravel)**. Basado en el modelado de dominio del PDF y en las rutas ya definidas en `frontend/src/router`.

**Regla:** ningún endpoint nuevo sin actualizar este archivo. Los JSON de ejemplo son la referencia para Postman y para mocks.

---

## 1. Modelo de dominio → tablas (Laravel)

| Entidad UML | Tabla sugerida | Notas |
|-------------|----------------|-------|
| Usuarios | `users` | Base: nombre, apellido, email, password, teléfono, foto_perfil, `role` |
| Clientes | — | `role = client` en `users` |
| Profesional | `professional_profiles` | `user_id`, título, descripción pública, etc. |
| Servicio | `services` | STI o `type`: `session` \| `package` |
| Sesión (tipo servicio) | campos en `services` | `url_video_llamada` (plantilla o por reserva) |
| Paquete | `services` + `package_purchases` | `cantidad_sesiones`, consumo vía `sesiones_restantes` |
| Cantidad | `package_purchases` | `sesiones_restantes` por compra de paquete |
| ubicacion | `locations` | ciudad, país, lat, lng — FK en `services` si presencial/híbrido |
| Agenda | `agendas` | 1:1 con profesional |
| ExcepcionAgenda | `agenda_exceptions` | fecha, motivo |
| Reserva | `bookings` | estado, fecha_hora, modalidad, FK cliente, servicio, opcional paquete_compra |
| Pago | `payments` | 1:1 con reserva o con compra de paquete |
| Calificacion | `reviews` | solo si reserva `finalizada` |
| Notificacion | `notifications` | in-app + disparo de email en cola |

**Admin:** no está en el UML; usar `role = admin` en `users` (requerimiento del enunciado).

---

## 2. Enumeraciones (valores exactos en API)

### `modalidad`
`virtual` | `presencial` | `hibrida`

### `booking_status` (EnumCicloVida)
`pendiente` | `confirmada` | `pagada` | `en_curso` | `finalizada` | `cancelada` | `no_asistida`

Transiciones permitidas (backend debe validar):

```
pendiente → confirmada | cancelada
confirmada → pagada | cancelada
pagada → en_curso | cancelada (según política)
en_curso → finalizada | no_asistida
finalizada → (solo review)
```

### `payment_status`
`pendiente` | `completado` | `fallido` | `reembolsado`

### `payment_method`
`tarjeta_debito` | `tarjeta_credito` | `paypal` (sandbox)

### `notification_type`
`confirmacion` | `recordatorio` | `cancelacion`

---

## 3. Roles y autorización

| Rol | Puede |
|-----|--------|
| `client` | Buscar, reservar, pagar, cancelar/reprogramar (políticas), calificar |
| `professional` | CRUD sus servicios, agenda, ver sus reservas, políticas de cancelación |
| `admin` | Listar usuarios, métricas básicas, moderación |

Auth: **Laravel Sanctum** — header `Authorization: Bearer {token}`.

---

## 4. Endpoints P0 (MVP entrega — semanas 1–2)

Base: `https://{host}/api`

### 4.1 Auth (ya consumido por `frontend/src/stores/auth.js`)

#### `POST /auth/register`
```json
// Request
{
  "nombre": "Ana",
  "apellido": "García",
  "email": "ana@mail.com",
  "password": "secret",
  "password_confirmation": "secret",
  "telefono": "+598...",
  "role": "client"
}
```
```json
// Response 201
{
  "user": {
    "id": 1,
    "nombre": "Ana",
    "apellido": "García",
    "email": "ana@mail.com",
    "telefono": "+598...",
    "foto_perfil": null,
    "role": "client"
  },
  "token": "1|..."
}
```

Registro profesional: mismo body con `"role": "professional"` + opcional `"titulo": "Coach deportivo"`.

#### `POST /auth/login`
```json
// Request
{ "email": "ana@mail.com", "password": "secret" }
```
Misma forma de `user` + `token` que register.

#### `GET /auth/me` — requiere auth

#### `POST /auth/logout` — requiere auth

---

### 4.2 Profesionales y servicios (páginas `/professionals`, `/professionals/:id`)

#### `GET /professionals`
Query: `search`, `modalidad`, `precio_min`, `precio_max`, `lat`, `lng`, `radius_km`, `sort` (`rating` \| `price`), `page`

```json
// Response 200
{
  "data": [
    {
      "id": 3,
      "nombre": "Carlos",
      "apellido": "Pérez",
      "titulo": "Nutricionista",
      "foto_perfil": "/storage/...",
      "rating_avg": 4.8,
      "rating_count": 12,
      "modalidades": ["presencial", "virtual"],
      "ubicacion": { "ciudad": "Montevideo", "pais": "UY", "latitud": -34.9, "longitud": -56.17 }
    }
  ],
  "meta": { "current_page": 1, "last_page": 2, "per_page": 15 }
}
```

#### `GET /professionals/{id}`
Incluye `servicios[]`, `agenda_resumen` (horario, días, buffer).

#### `GET /professionals/{id}/services`
Lista servicios del profesional (sesión suelta o paquete).

```json
{
  "data": [
    {
      "id": 10,
      "type": "session",
      "nombre": "Consulta inicial",
      "descripcion": "...",
      "duracion": 60,
      "precio": 1500,
      "modalidad": "presencial",
      "ubicacion": { "ciudad": "Montevideo", "pais": "UY", "latitud": -34.9, "longitud": -56.17 }
    },
    {
      "id": 11,
      "type": "package",
      "nombre": "Pack 8 sesiones",
      "cantidad_sesiones": 8,
      "precio": 10000,
      "modalidad": "virtual"
    }
  ]
}
```

---

### 4.3 Disponibilidad (página `/book/:professionalId`)

#### `GET /professionals/{id}/availability`
Query: `service_id`, `from` (ISO date), `to` (ISO date)

El backend aplica: `Agenda` (horario, días, buffer) + `ExcepcionAgenda` + reservas existentes + **bloqueo transaccional** al crear reserva.

```json
// Response 200
{
  "slots": [
    { "start": "2026-06-10T09:00:00-03:00", "end": "2026-06-10T10:00:00-03:00", "available": true },
    { "start": "2026-06-10T10:00:00-03:00", "end": "2026-06-10T11:00:00-03:00", "available": false }
  ]
}
```

---

### 4.4 Reservas

#### `POST /bookings` — cliente, auth
```json
{
  "service_id": 10,
  "professional_id": 3,
  "fecha_hora": "2026-06-10T09:00:00-03:00",
  "modalidad": "presencial",
  "package_purchase_id": null
}
```
Si consume sesión de paquete: `package_purchase_id` obligatorio; decrementar `sesiones_restantes`.

**Concurrencia:** `DB::transaction` + fila bloqueada en slot o unique `(professional_id, fecha_hora)`.

```json
// Response 201
{
  "id": 50,
  "estado": "pendiente",
  "fecha_hora": "2026-06-10T09:00:00-03:00",
  "modalidad": "presencial",
  "service": { "id": 10, "nombre": "Consulta inicial", "duracion": 60 },
  "professional": { "id": 3, "nombre": "Carlos", "apellido": "Pérez" },
  "payment": { "id": 20, "estado": "pendiente", "monto": 1500 }
}
```

#### `GET /bookings` — auth
- Cliente: sus reservas
- Profesional: reservas de sus servicios
Query: `estado`, `from`, `to`

#### `GET /bookings/{id}`

#### `PATCH /bookings/{id}/cancel`
Body opcional: `{ "motivo": "..." }` — validar política (ej. mínimo 24h antes).

#### `PATCH /bookings/{id}/reschedule`
```json
{ "fecha_hora": "2026-06-11T09:00:00-03:00" }
```

#### `PATCH /bookings/{id}/status` — solo profesional/admin
```json
{ "estado": "confirmada" }
```

---

### 4.5 Agenda del profesional (dashboard profesional)

#### `GET /professional/agenda` — auth professional

#### `PUT /professional/agenda`
```json
{
  "horario_inicio": "09:00",
  "horario_fin": "18:00",
  "dias_disponibles": [1, 2, 3, 4, 5],
  "buffer_minutos": 15
}
```

#### `POST /professional/agenda/exceptions`
```json
{ "fecha": "2026-07-18", "motivo": "Feriado" }
```

#### `DELETE /professional/agenda/exceptions/{id}`

---

## 5. Endpoints P1 (semana 3 — puntaje y visión)

| Módulo | Endpoints |
|--------|-----------|
| Paquetes | `POST /package-purchases` (comprar), `GET /package-purchases` (mis paquetes) |
| Pagos | `POST /payments/{id}/paypal` (sandbox), webhook simulado |
| Reviews | `POST /bookings/{id}/review` `{ "puntaje": 4.5, "comentario": "..." }` |
| Notificaciones | `GET /notifications`, `PATCH /notifications/{id}/read` |
| Admin | `GET /admin/users`, `GET /admin/metrics` |
| Videollamada | `POST /bookings/{id}/livekit-token` cuando `modalidad=virtual` y estado `en_curso` |

---

## 6. Tiempo real y async (arquitectura PDF)

| Componente | Uso |
|------------|-----|
| **Redis** | Colas (`QUEUE_CONNECTION=redis`), cache de slots opcional |
| **Queue workers** | Emails, recordatorios, notificaciones |
| **Scheduler** | `bookings:send-reminders` cada hora (turnos en 24h) |
| **WebSocket** | Broadcast al profesional cuando nueva reserva / cambio estado (Laravel Echo + Pusher o Soketi) |
| **SMTP** | Mail en cola tras crear/cancelar reserva |
| **PayPal sandbox** | SDK oficial → `referencia_pasarela` en `payments` |
| **Google OAuth** | Socialite → mismo token Sanctum al final |
| **LiveKit** | Token efímero por reserva virtual |
| **Google Maps / Leaflet** | Front consume API key; backend devuelve lat/lng en `ubicacion` |

---

## 7. Mapeo Vue ↔ API

| Ruta Vue | Endpoint principal |
|----------|-------------------|
| `/auth/login`, `/auth/register` | `/auth/*` |
| `/professionals` | `GET /professionals` |
| `/professionals/:id` | `GET /professionals/{id}` |
| `/book/:professionalId` | `GET .../availability` + `POST /bookings` |
| `/dashboard/client` | `GET /bookings`, `GET /package-purchases` |
| `/dashboard/professional` | `GET /bookings`, `PUT /professional/agenda` |
| `/admin` | `GET /admin/*` |

---

## 8. Decisiones abiertas (cerrar en reunión de 30 min)

1. **Sesión en UML** con `horarioInicio/Fin`: ¿plantilla del servicio o instancia generada por cada **Reserva**?  
   **Recomendación:** horarios reales solo en `bookings.fecha_hora` + duración del `service`; `url_video_llamada` se genera al pasar a `en_curso`.

2. **Paquete:** la compra crea `package_purchase` con `sesiones_restantes`; cada `POST /bookings` con `package_purchase_id` consume 1.

3. **Política de cancelación:** campo en `professional_profiles` — `cancelacion_horas_minimas` (entero).

---

## 9. Orden de implementación (4 personas)

| Orden | Tarea | Responsable sugerido |
|-------|--------|----------------------|
| 1 | Aprobar este documento | Todas |
| 2 | `backend/` Laravel + migraciones P0 + Sanctum | Dev backend A |
| 3 | Seeders (2 profesionales, 5 servicios, 1 agenda) | Dev backend A |
| 4 | Auth + login en Vue con API real | Dev front B |
| 5 | Listado/detalle profesionales | Dev front B |
| 6 | Availability + POST booking + concurrencia | Dev backend B |
| 7 | BookingPage conectada | Dev front B |
| 8 | Colas + emails confirmación/recordatorio | Dev transversal |
| 9 | P1 (pagos, paquetes, mapa, PWA, deploy) | Según puntaje |

---

*Versión: 0.1 — 2026-05-21*
