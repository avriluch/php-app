# Contexto del proyecto

Documento de referencia con la letra UTEC, el modelo de dominio (UML), la arquitectura propuesta y las decisiones técnicas tomadas. Pensado para que un asistente nuevo entienda **qué se está construyendo y por qué** sin necesidad de las imágenes originales.

---

## 1. Información administrativa

- **Materia:** Desarrollo de Aplicaciones Web con PHP
- **Carrera:** Tecnólogo Informático, UTEC
- **Curso:** 2026
- **Inicio del laboratorio:** 23 de abril 2026
- **Entrega final:** **domingo 21 de junio 2026 — 23:59 hs** (Moodle)
- **Defensa (presentación + demo):** **jueves 25 de junio 2026**
- **Pre-entrega de análisis y prototipo:** 11 de mayo 2026 (ya pasada)
- **Tamaño del grupo:** 4 integrantes

---

## 2. Visión del producto (resumen de la letra)

**Plataforma integral de gestión de servicios profesionales** que permite a profesionales independientes y pequeñas organizaciones (consultores, entrenadores personales, asesores, educadores, salud no clínica, técnicos) publicar servicios, gestionar agenda, atender clientes en diferentes modalidades y comercializar paquetes de sesiones.

### Roles
- **Cliente**: explora servicios, reserva, paga, cancela/reprograma, califica.
- **Profesional**: configura perfil, define servicios y precios, configura agenda con reglas (horario, días, buffers, excepciones), atiende reservas.
- **Admin** (no está en UML pero la letra lo pide): gestiona usuarios, monitorea, ve métricas.

### Modalidades
- **Presencial** — con ubicación física asociada
- **Virtual** — con videollamada integrada
- **Híbrida** — definida por el profesional

### Ciclo de vida de una reserva
`Pendiente → Confirmada → Pagada → En curso → Finalizada` con ramas a `Cancelada` y `No asistida`.

### Funcionalidades obligatorias
Registro y autenticación · Gestión de perfiles profesionales · Configuración de disponibilidad · Búsqueda y filtrado · Reserva de turnos · Gestión de reservas · Paquetes · Pagos · Videollamadas · Calificaciones · Notificaciones · Panel administrativo.

### Requisitos NO funcionales obligatorios
- Diseño responsive
- API REST
- Auth con control de roles
- Notificaciones por email
- Mapa (Google Maps o Leaflet)
- Sistema de reseñas
- Videollamadas (LiveKit Cloud o WebRTC)
- Tiempo real con WebSockets (Echo / Pusher / Socket.io)

### Electivos disponibles (con puntos)
| Electivo | Pts |
|----------|-----|
| Pasarela de pago (PayPal) | 2 |
| Control de concurrencia en reservas | 3 |
| Recordatorios automáticos | 2 |
| Dockerización | 2 |
| CI/CD | 3 |
| Colas asincrónicas (Redis) | 3 |
| NoSQL para logs | 1 |
| OAuth con redes sociales | 2 |
| Publicación en la nube con HTTPS | 2 |
| PWA | 3 |
| Arquitectura desacoplada (frontend separado) | 4 |

### Requisito de puntaje
- Grupos de 3 → **mínimo 15 puntos electivos**
- Grupos de 4 (es nuestro caso) → **mínimo 20 puntos electivos**

### Electivos elegidos por nuestro grupo
| Electivo | Pts | Estado |
|----------|-----|--------|
| Arquitectura desacoplada | 4 | ✅ |
| OAuth Google | 2 | ✅ |
| Concurrencia reservas | 3 | ✅ |
| Colas async | 3 | ✅ |
| Recordatorios automáticos | 2 | ✅ |
| PWA | 3 | ⏳ frontend pendiente |
| Nube + HTTPS | 2 | ⏳ pendiente |
| PayPal sandbox | 2 | ✅ (backend listo, frontend conectado) |
| **Total objetivo** | **21** | **14 confirmados, 7 en camino** |

---

## 3. Modelo de dominio (UML transcrito)

Diagramas originales en imágenes adjuntas al chat. Lo que sigue es la transcripción.

### Clases

#### Usuarios (clase base)
Campos: `nombre: string`, `apellido: string`, `email: string`, `contraseña: string`, `telefono: string`, `fotoPerfil`.
Subclases por herencia: **Clientes**, **Profesional**.

#### Clientes
Hereda de Usuarios. Sin campos propios destacados.

#### Profesional
Hereda de Usuarios. Campos: `titulo: string`.

#### Servicio (clase base)
Campos: `nombre: string`, `descripcion: string`, `duracion: int`, `precio: float`, `modalidadServicio: EnumModalidad`.
Subclases: **Sesion**, **Paquete**.

#### Sesion
Hereda de Servicio. Campos: `urlVideoLlamada: string`, `horarioInicio: time`, `horarioFin: time`.

#### Paquete
Hereda de Servicio. Campos: `nombre: string`, `cantidadSesiones: int`.

#### Cantidad
Campos: `sesionesRestantes: int`. Relacionada con Paquete.

#### Reserva
Campos: `id: int`, `estado: EnumCicloVida`, `fechaHora: datetime`, `modalidadReserva: EnumModalidad`.

#### Pago
Campos: `id: int`, `monto: float`, `estado: EnumEstadoPago`, `metodo: EnumMetodoPago`, `fechaPago: datetime`, `referenciaPasarela: string`.

#### Calificacion
Campos: `id: int`, `puntaje: float`, `comentario: string`, `fecha: datetime`.

#### Notificacion
Campos: `id: int`, `tipo: EnumTipoNotificacion`, `fechaEnvio: datetime`, `mensaje: string`.

#### Agenda
Campos: `horarioInicio: time`, `horarioFin: time`, `diasDisponibles: set[]`, `bufferMinutos: int`. Relación 1:1 con Profesional.

#### ExcepcionAgenda
Campos: `fecha: date`, `motivo: string`. Relacionada con Agenda.

#### ubicacion
Campos: `ciudad: string`, `pais: string`, `longitud: float`, `latitud: float`.

### Relaciones principales
- Usuarios ─tiene─► Calificacion (0..*)
- Usuarios ─recibe─► Notificacion (0..*)
- Cliente ─realiza─► Notificacion (1..*) — interpretado como dispara
- Cliente ─realiza─► Reserva (1..*)
- Profesional ─tiene─► Agenda (1:1)
- Profesional ─ofrece─► Servicio (1..*)
- Agenda ─tiene─► ExcepcionAgenda (0..*)
- Servicio ─se ubica en─► ubicacion (0..1)
- Reserva ─tiene─► Pago (1:1)
- Reserva ─es de─► Servicio (Sesion concretamente) (1)
- Paquete ─contiene─► Sesion (1..*)
- Paquete ─tiene─► Cantidad (1..*) — una por compra/cliente

### Enumeraciones

```
EnumTipoNotificacion : confirmacion | recordatorio | cancelacion
EnumModalidad         : virtual | presencial | hibrida
EnumCicloVida         : Pendiente | Confirmada | Pagada | En curso | Finalizada | Cancelada | No asistida
EnumEstadoPago        : Pendiente | Completado | Fallido | Reembolsado
EnumMetodoPago        : Tarjeta de debito | Tarjeta de credito
```

---

## 4. Arquitectura (transcripción del diagrama)

```
┌─────────────────┐                ┌─────────────────────────────────┐                ┌───────────┐
│ CLIENTE         │                │   BACKEND Laravel               │  MySQL/TCP-IP  │ MariaDB   │
│ Vue PWA         │  HTTPS / REST  │  ┌────────────────┐  ┌────────┐ │◄──────────────►│           │
│ (desacoplado)   ├───────────────►│  │ Controladores  │  │ Queue  │ │                └───────────┘
│                 │                │  │ REST           │  │ Workers│ │                ┌───────────┐
│ Responsive      │   WebSocket    │  │ - Reservas     │  │ + Sched│ │                │ Redis     │
│ Service Worker  │◄──────────────►│  │ - Paquetes     │  │ + Cron │ │◄──────────────►│           │
│ Push notifs     │                │  │ - Pagos        │  │ + Email│ │                └───────────┘
└─────────────────┘                │  └────────────────┘  └────────┘ │
                                   │  ┌────────────────┐  ┌────────┐ │                ┌─────────────────┐
                                   │  │ WebSocket Srv  │  │ Auth + │ │                │ SERVICIOS EXT.  │
                                   │  │ (Reverb)       │  │ OAuth  │ │◄──────────────►│ PayPal · Google │
                                   │  │ Notif live     │  │ Roles  │ │                │ OAuth · LiveKit │
                                   │  └────────────────┘  └────────┘ │                │ Maps · SMTP     │
                                   └─────────────────────────────────┘                └─────────────────┘
```

Componentes:
- **Cliente Vue PWA** (responsive, Service Worker, notificaciones push). Se comunica con backend vía HTTPS REST y WebSocket.
- **Backend Laravel**: API REST + Queue Workers + WebSocket Server (Reverb) + capa de Auth/OAuth con control de roles.
- **MariaDB** vía protocolo MySQL TCP/IP.
- **Redis** para cola y cache.
- **Servicios externos:** PayPal sandbox, Google OAuth, LiveKit (videollamadas), Google Maps, SMTP.

---

## 5. Decisiones técnicas y desviaciones del UML

Estas decisiones están **cerradas** y documentadas. Si alguien las quiere cambiar, escalar antes.

### 5.1 Herencia de Usuario aplanada
Decisión: una tabla `users` con columna `role` (`client` | `professional` | `admin`).
Razón: STI evita 3 tablas con FK cruzadas, y simplifica el query típico "buscar por email".
El UML solo modela Cliente y Profesional; Admin se agrega porque la letra lo pide.

### 5.2 Servicio / Sesion / Paquete aplanados
Decisión: una tabla `services` con columna `type` (`session` | `package`).
Razón: las tres comparten 90% de los campos (nombre, descripción, duración, precio, modalidad). Separarlas en herencia complicaba reportes y catálogos.
- `cantidad_sesiones` solo aplica si `type=package`.
- `duracion` obligatoria si `type=session`.

### 5.3 Sesion en UML como **instancia** (no como plantilla)
El UML pone `horarioInicio` y `horarioFin` en Sesion. **Decidimos**: los horarios reales viven en `bookings.fecha_hora` + `services.duracion`. La Sesion del UML se materializa al momento de reservar.
Razón: si Sesion fuera plantilla, habría dos lugares con horarios (servicio y reserva) y sería incoherente.

### 5.4 `urlVideoLlamada` por reserva, no por servicio
El UML lo pone en Sesion. **Decidimos**: vive en `bookings.url_video_llamada` y se genera al pasar a `en_curso` si la modalidad es virtual.
Razón: cada reserva necesita su propia sala. Una plantilla compartida no escala.

### 5.5 `Cantidad` → `package_purchases`
Mapeo directo. Una fila por compra de un cliente. Campo `sesiones_restantes` se decrementa al reservar y se incrementa al cancelar.

### 5.6 `EnumMetodoPago` extendido con `paypal`
El UML solo tiene débito y crédito. **Decidimos**: agregar `paypal` porque la letra incluye el electivo "Integración con pasarela de pago (PayPal)".
Razón: la letra prevalece sobre el UML.

### 5.7 Política de cancelación
Campo nuevo: `professional_profiles.cancelacion_horas_minimas`. Cada profesional define cuántas horas antes acepta cancelaciones de clientes.
Razón: la letra menciona "tiempos mínimos de cancelación" definidos por el profesional.

### 5.8 Pago para reservas que consumen paquete
Decisión: **no se crea un `Payment`** cuando una reserva consume una sesión del paquete. El pago vive en el `Payment` del `package_purchase` (que se creó cuando el cliente compró el paquete).
Razón: el cliente ya pagó. Generar otro Payment con monto 0 era ruido.

### 5.9 Concurrencia: triple capa
1. `UNIQUE(professional_profile_id, fecha_hora)` en la tabla `bookings`.
2. `DB::transaction()` en `BookingController::store`.
3. `Service::lockForUpdate()` y `PackagePurchase::lockForUpdate()` dentro de la transacción.
Si dos clientes llegan al mismo slot a la vez, el segundo recibe 422.

### 5.10 Notificaciones: tabla propia + sistema de Laravel ignorado
Decisión: usamos una tabla `notifications` custom (modelo `App\Models\Notification`), no el sistema de notificaciones polimórfico de Laravel.
Razón: la tabla del UML tiene campos específicos (`tipo`, `mensaje`, `fecha_envio`, `read_at`) y queremos JOINs naturales con `bookings`.
El `User` tiene un método `notifications()` que sobrescribe el de Notifiable.

### 5.11 Mailable + Job + Notificación: una sola tarea async
Cuando ocurre una acción importante (crear/cancelar reserva, recordatorio), se dispatchea un único Job que:
1. Crea el registro en `notifications`.
2. Envía el email.
3. (Sólo el job de creación) emite el Event WebSocket para el profesional.

Razón: una sola unidad de trabajo, fácil de testear y reintentar.

### 5.12 Driver de mail en dev
`MAIL_MAILER=log` — los emails se escriben a `storage/logs/laravel.log`. Para la defensa cambiamos a Mailtrap u otro SMTP real.

### 5.13 WebSocket: Reverb (oficial Laravel 11)
Servidor propio en :8080. Protocolo Pusher. Cliente: Laravel Echo + pusher.js. Canales privados autenticados con Sanctum.

---

## 6. Mapeo UML ↔ implementación

| Entidad UML | Tabla | Modelo |
|-------------|-------|--------|
| Usuarios | `users` | `App\Models\User` |
| Clientes | `users.role = client` | (sin clase aparte) |
| Profesional | `professional_profiles` | `App\Models\ProfessionalProfile` |
| Servicio | `services` | `App\Models\Service` |
| Sesion / Paquete | `services.type` | (sin clases aparte) |
| Cantidad | `package_purchases` | `App\Models\PackagePurchase` |
| Reserva | `bookings` | `App\Models\Booking` |
| Pago | `payments` | `App\Models\Payment` |
| Calificacion | `reviews` | `App\Models\Review` |
| Notificacion | `notifications` | `App\Models\Notification` |
| Agenda | `agendas` | `App\Models\Agenda` |
| ExcepcionAgenda | `agenda_exceptions` | `App\Models\AgendaException` |
| ubicacion | `locations` | `App\Models\Location` |

Enums:
| Enum UML | Enum PHP |
|----------|----------|
| EnumModalidad | `App\Enums\Modalidad` |
| EnumCicloVida | `App\Enums\BookingStatus` |
| EnumEstadoPago | `App\Enums\PaymentStatus` |
| EnumMetodoPago | `App\Enums\PaymentMethod` (extendido con `paypal`) |
| EnumTipoNotificacion | `App\Enums\NotificationType` |
| — | `App\Enums\ServiceType` (session/package, surge del aplanado) |
| — | `App\Enums\UserRole` (client/professional/admin, surge del aplanado) |
