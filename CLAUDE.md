# Instrucciones para Claude — Proyecto `php-app`

Punto de entrada para asistentes de IA que vengan a trabajar este repo.
Para contexto profundo leer también `docs/proyecto.md` (qué es) y `docs/estado-actual.md` (qué está hecho).

---

## Qué es este proyecto

**Plataforma de gestión de servicios profesionales** — laboratorio para la materia **Desarrollo de Aplicaciones Web con PHP** de **UTEC, Tecnólogo Informático, curso 2026**.

Grupo de 4 integrantes. Entrega final **21 de junio 2026**, defensa **25 de junio**.

Permite a profesionales independientes (nutricionistas, coaches, etc.) ofrecer servicios, gestionar agenda y vender paquetes; los clientes reservan, pagan y califican.

## Stack

- **Backend:** PHP 8.5 + Laravel 11 + Sanctum + Socialite + Reverb
- **BD:** MariaDB 10.4 (XAMPP) en puerto **3307**
- **Cola + cache:** Redis con cliente `predis`
- **Frontend:** Vue 3 + Vite + Pinia + Tailwind 4 + axios + Laravel Echo
- **Monorepo:** `backend/` (Laravel) + `frontend/` (Vue) + `docs/` (contratos)

## Cómo correrlo

```powershell
# 1. Servicios base (una vez)
# - XAMPP MySQL/MariaDB (puerto 3307)
# - redis-server

# 2. Backend
cd backend
composer install
copy .env.example .env   # editar DB_PORT=3307 y demás
php artisan key:generate
php artisan migrate --seed   # crea 3 usuarios demo
php artisan serve            # API en :8000
php artisan queue:work redis # worker async (otra terminal)
php artisan reverb:start     # WebSocket en :8080 (otra terminal)

# 3. Frontend
cd frontend
npm install
npm run dev   # :5173
```

Usuarios demo (password: `password`):
- `admin@demo.test`
- `cliente@demo.test`
- `profesional@demo.test`

## Cómo querés que escriba código

### Idioma
- **Identificadores propios en español** (variables locales, parámetros, métodos privados, comentarios, mensajes de error y UI).
- **Inglés solo para vocabulario técnico estándar y APIs del framework**: nombres de clase tipo `Controller`/`Model`/`Resource`, métodos de Laravel (`store`, `index`, `show`, `update`, `destroy`, `handle`, `findOrFail`, `lockForUpdate`), convenciones REST (rutas plurales `/bookings`, `/payments`), anglicismos consolidados (`commit`, `branch`, `lint`, `mock`, `seed`, `queue`, `worker`, `cache`, `payload`, `slot`, `buffer`, `token`).
- Las **columnas de BD y enums de negocio** ya están en español (`fecha_hora`, `cancel_motivo`, `Modalidad`, `cancelacion_horas_minimas`) — mantener esa convención.

### Simplicidad
**Proyecto académico de equipo mixto** (4 personas con niveles distintos). Optar por la solución más directa que cumpla el requisito.

Evitar:
- Repository / CQRS / Hexagonal / Event Sourcing.
- Event/Listener cuando alcanza con dispatchear un Job desde el controller.
- DTOs, value objects, factories que no aporten nada concreto.
- Service Container bindings o providers custom.
- Helpers o macros que oculten qué hace el código.

Preferir:
- Controllers que llaman directo a Models y Jobs.
- Validación inline con `$request->validate([...])`.
- Una clase = una responsabilidad clara.
- Eloquent directo en vez de query builders complicados.

### Patrones que sí usamos
MVC + API REST + Active Record (Eloquent) + API Resources + Service Layer mínima (`AvailabilityService`) + Queue/Worker + Event + Broadcasting + State Machine simple (`BookingStatus::canTransitionTo`) + Middleware (`auth:sanctum`, `role:...`) + Enums tipados.

## Estructura del repo

```
php-app/
├── CLAUDE.md                 ← este archivo
├── README.md                 ← guía para humanos
├── docs/
│   ├── api-contract-v0.md    ← contrato API (canónico, no romper)
│   ├── proyecto.md           ← contexto académico, UML, decisiones
│   └── estado-actual.md      ← qué está hecho y qué falta
├── backend/                  ← Laravel 11
└── frontend/                 ← Vue 3 SPA
```

## Reglas de oro al modificar este repo

1. **Antes de tocar código, leer `docs/api-contract-v0.md`**. Si vas a agregar/cambiar un endpoint, actualizar el contrato en el mismo PR.
2. **No commitear el `.env`**. Ya está en `.gitignore`.
3. **Hablar en español al usuario y al código** (ver "Idioma" arriba).
4. **Mantener simplicidad** (ver "Simplicidad" arriba).
5. Las **migraciones P0 ya están aplicadas** y no se deben cambiar — si necesitás tocar la estructura, crear una nueva migración.
6. Las **decisiones de modelo de datos** (sesion como instancia y no plantilla, paquete con `cantidad_sesiones`, política de cancelación con `cancelacion_horas_minimas`) están cerradas — están documentadas en `docs/proyecto.md`.

## Branch y commits

- Branch principal: `main`.
- Branch de trabajo backend: `feature/backend-mvp` (donde vive todo el trabajo reciente; ya pusheada a `origin`).
- Convención de mensajes: en español, descriptivo, sin tags de coautor automáticos a menos que se pidan.

## Para chats nuevos: orden recomendado de lectura

1. Este archivo (`CLAUDE.md`)
2. `docs/proyecto.md` — qué se está construyendo y por qué
3. `docs/estado-actual.md` — qué hay hoy en el código
4. `docs/api-contract-v0.md` — contrato de endpoints
5. `backend/routes/api.php` — endpoints reales
6. `backend/database/migrations/` — esquema de BD
