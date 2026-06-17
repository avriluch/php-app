# Backend — API Laravel

API REST desacoplada del frontend Vue. Contrato: [`../docs/api-contract-v0.md`](../docs/api-contract-v0.md).

## Requisitos

- PHP 8.2+ (XAMPP: `C:\xampp\php\php.exe`)
- Composer 2.x
- MySQL / MariaDB (XAMPP)
- Extensión PHP: `pdo_mysql`, `openssl`, `mbstring`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`

## Instalación (primera vez)

```powershell
cd c:\UTEC\PHP\php-app\backend

# Si composer no está en PATH, usar ruta completa o instalar desde getcomposer.org
composer install

copy .env.example .env
php artisan key:generate
```

Crear base de datos en phpMyAdmin:

```sql
CREATE DATABASE php_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Ajustar `.env`:

```
DB_DATABASE=php_app
DB_USERNAME=root
DB_PASSWORD=
FRONTEND_URL=http://localhost:5173
```

Migrar y datos demo:

```powershell
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

API en `http://localhost:8000/api/health`.

## Usuarios de prueba (seeder)

| Email | Rol | Password |
|-------|-----|----------|
| admin@demo.test | admin | password |
| cliente@demo.test | client | password |
| profesional@demo.test | professional | password |

## Google OAuth (login con Google)

1. En [Google Cloud Console](https://console.cloud.google.com/apis/credentials) creá un **OAuth 2.0 Client ID** (tipo *Web application*).
2. **Authorized redirect URI:** `http://localhost:8000/api/auth/google/callback`
3. En `backend/.env`:

```
GOOGLE_CLIENT_ID=tu-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=tu-secret
GOOGLE_REDIRECT_URI=http://localhost:8000/api/auth/google/callback
```

4. Instalá dependencias y migrá:

```powershell
cd backend
composer install
php artisan migrate
```

5. Desde Vue (`/auth/login`), botón **Continuar con Google** → Google → vuelve a `/auth/oauth-callback` con sesión.

Sin credenciales en `.env`, el redirect responde JSON 503 con instrucciones (ya no 404).

## Auth (ya implementado)

- `POST /api/auth/register`
- `POST /api/auth/login`
- `GET /api/auth/me` (Bearer token)
- `POST /api/auth/logout`

Conectar Vue: `VITE_API_URL=http://localhost:8000/api` en `frontend/.env`.

**Nota:** La API usa tokens Bearer (`Authorization: Bearer …`), no cookies de sesión. Si ves `CSRF token mismatch`, no agregues `EnsureFrontendRequestsAreStateful` a las rutas `/api/*`.

## Pendiente de implementar (controllers 501)

| Controller | Endpoints |
|------------|-----------|
| `ProfessionalController` | ~~index, show~~ ✅ · servicios, availability pendientes |
| `BookingController` | CRUD reservas, cancel, reschedule, status |
| `AgendaController` | agenda del profesional |
| `AdminController` | usuarios, métricas |

Ver stubs en `app/Http/Controllers/Api/` y `app/Services/AvailabilityService.php`.

## Estructura del dominio (migraciones P0)

```
users
professional_profiles → locations
services (session | package)
agendas → agenda_exceptions
package_purchases
bookings (unique professional + fecha_hora)
payments
reviews
notifications
```

Enums en `app/Enums/`. Modelos en `app/Models/`.

## Colas (emails y notificaciones)

Los emails de reserva se encolan (`ShouldQueue`). **Sin un worker activo, no se envían.**

```powershell
# Terminal aparte mientras desarrollás
php artisan queue:work

# Recordatorios T+24h (scheduler)
php artisan schedule:work
```

WebSockets en tiempo real (Reverb):

```powershell
php artisan reverb:start
```

## Despliegue en Railway

El servicio web **solo** atiende HTTP. Para emails, recordatorios y WebSockets necesitás **servicios adicionales** (mismo repo, distinto *Start Command*):

| Servicio | Start Command |
|----------|---------------|
| **API (web)** | `php artisan storage:link --force && php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=$PORT` |
| **Worker (colas)** | `php artisan queue:work --sleep=3 --tries=3 --max-time=3600` |
| **Scheduler** | `php artisan schedule:work` |
| **Reverb (opcional)** | `php artisan reverb:start --host=0.0.0.0 --port=$PORT` |

Variables críticas en Railway:

```
BREVO_API_KEY=...                    # obligatorio para emails
MAIL_FROM_ADDRESS=...                # debe estar verificado como remitente en Brevo
QUEUE_CONNECTION=database
BROADCAST_CONNECTION=reverb          # no uses "log" si querés WebSockets
SANCTUM_STATEFUL_DOMAINS=frontend-production-08bf.up.railway.app,localhost,localhost:5173
```

En el **frontend** de Railway agregá las vars Reverb apuntando al servicio Reverb público:

```
VITE_REVERB_APP_KEY=<mismo que REVERB_APP_KEY del backend>
VITE_REVERB_HOST=<host público del servicio Reverb>
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
```

Si no levantás Reverb, los emails igual pueden funcionar con el **worker**; los WebSockets en vivo no.

## Comandos útiles

```powershell
php artisan route:list --path=api
php artisan migrate:fresh --seed
php artisan tinker
```
