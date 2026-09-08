# AGENTS.md — Travel-Booking

## Project Overview
Monorepo: Laravel 11 backend (`backend/`) + React 19 frontend (`frontend/`). Multi-hotel booking platform with Stripe/PayPal payments, vendor payouts, and dynamic website settings.

## Key Commands

### Backend (run from `backend/`)
```bash
composer install          # install PHP deps
cp .env.example .env      # no .env.example exists; copy .env manually
php artisan key:generate
php artisan migrate       # requires DB configured in .env
php artisan serve         # API at http://localhost:8000/api/v1
php artisan queue:work    # required for async jobs (webhooks, emails)
php artisan schedule:work # for scheduled tasks (prod: cron)

# Testing (uses Pest, sqlite in-memory)
php artisan test                  # all tests
php artisan test --filter Name    # single test
php artisan test --coverage       # with coverage

# Dev (runs server, queue, logs, vite concurrently)
composer dev
```

### Frontend (run from `frontend/`)
```bash
npm install
npm run dev        # http://localhost:5173 (Vite proxy /api → backend)
npm run build      # production build to dist/
npm run lint       # ESLint (flat config)
```

## Architecture Notes
- **Clean Architecture** in `backend/app/`: Actions, DTOs, Enums, Services, Repositories (Contracts + Eloquent), Models, Policies, Http/Controllers/Requests/Resources
- **Multi-vendor isolation**: all data scoped by `hotel_id` — never leak data between vendors
- **API**: `/api/v1` in `routes/api.php`, Sanctum token auth
- **Admin dashboards**: Blade + Tailwind at `/admin` (super admin) and `/admin/vendor` (vendor)
- **Frontend**: React + React Query + Axios, pages in `src/pages/`, components in `src/components/`

## Critical Conventions
- Controllers = thin orchestration only; business logic in Services/Actions
- Repositories = all DB queries; services use repository interfaces
- Enums for all domain values (BookingStatus, PaymentStatus, UserRole, etc.)
- UUID primary keys, soft deletes, polymorphic audit logs
- Form Requests for validation (reused in API and Blade)

## Environment & Setup Gotchas
- No `.env.example` in backend — copy `.env` manually and configure:
  - `DB_CONNECTION` (mysql/pgsql), `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
  - `REDIS_HOST`, `QUEUE_CONNECTION=redis` (required for async jobs)
  - `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`, `STRIPE_KEY` (see `backend/PAYMENTS.md`)
- Frontend: create `.env.local` with `VITE_API_URL=http://localhost:8000/api/v1` if not using Vite proxy
- Redis required for queues; `QUEUE_CONNECTION=sync` only for local testing

## Testing
- Pest (PHP) — Unit tests in `tests/Unit`, Feature in `tests/Feature`
- SQLite in-memory for tests (configured in `phpunit.xml`)
- Run `php artisan config:clear` before tests (in composer test script)

## Code Style
- Backend: Laravel Pint (run `./vendor/bin/pint` or `composer pint` if added)
- Frontend: ESLint flat config (`npm run lint`)

## Deployment Notes
- Set `APP_ENV=production`, `APP_DEBUG=false`
- Run migrations with `--force`
- Supervisor for queue worker, cron for scheduler
- Frontend: static deploy (Vercel/Netlify/S3+CloudFront), API URL in env

## Files to Reference
- `README.md` — full project docs
- `backend/app/README.md` — folder conventions
- `backend/docs/INFRASTRUCTURE.md` — queue/scheduler setup
- `backend/PAYMENTS.md` — Stripe flow, webhooks, refunds
- `composer.json` scripts: `setup`, `dev`, `test`