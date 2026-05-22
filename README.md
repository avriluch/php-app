# php-app — Plataforma de servicios profesionales

Monorepo desacoplado:

| Carpeta | Stack |
|---------|--------|
| [`frontend/`](frontend/) | Vue 3 + Vite + Pinia |
| [`backend/`](backend/) | Laravel 11 API + Sanctum |
| [`docs/`](docs/) | Contrato API (`api-contract-v0.md`) |

## Inicio rápido

### 1. Backend (XAMPP + Composer)

Ver [`backend/README.md`](backend/README.md):

```powershell
cd backend
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

### 2. Frontend

```powershell
cd frontend
copy .env.example .env
npm install
npm run dev
```

### Pre-requisitos

- [XAMPP](https://www.apachefriends.org/) (PHP + MySQL)
- [Composer](https://getcomposer.org/download/)
- [Node.js](https://nodejs.org/es/download) (LTS)

```powershell
php -v
composer -V
node -v
npm -v
```
