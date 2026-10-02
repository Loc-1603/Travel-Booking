# AGENTS.md — Travel-Booking

## Project Overview
Monorepo: Laravel 12 backend (`backend/`) + React 19 frontend (`frontend/`). Multi-hotel booking platform with VNPay payments, vendor payouts, dynamic website settings, tour bookings, and Reverb realtime.

## Key Commands

### Backend (run from `backend/`)
```bash
composer install          # install PHP deps
cp .env .env.local        # no .env.example exists; copy .env manually
php artisan key:generate
php artisan migrate       # requires DB configured in .env
php artisan serve         # API at http://localhost:8000/api/v1
php artisan queue:work    # required for async jobs (webhooks, emails, Reverb)
php artisan schedule:work # for scheduled tasks (prod: cron)
php artisan reverb:start  # for realtime tour chat (dev)

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
npm run test       # Vitest unit tests
npm run test:watch # Vitest watch mode
```

## Architecture Notes
- **Clean Architecture** in `backend/app/`: Actions, DTOs, Enums, Services, Repositories (Contracts + Eloquent), Models, Policies, Http/Controllers/Requests/Resources
- **Multi-vendor isolation**: all data scoped by `hotel_id` — never leak data between vendors
- **API**: `/api/v1` in `routes/api.php`, Sanctum token auth
- **Admin dashboards**: Blade + Tailwind at `/admin` (super admin) and `/admin/vendor` (vendor)
- **Frontend**: React + React Query + Axios, pages in `src/pages/`, components in `src/components/`
- **Realtime**: Laravel Reverb for tour chat (events: TourMessageSent, TourMessageInboxUpdated, TourMessageRead)

## Critical Conventions
- Controllers = thin orchestration only; business logic in Services/Actions
- Repositories = all DB queries; services use repository interfaces
- Enums for all domain values (BookingStatus, PaymentStatus, UserRole, etc.)
- UUID primary keys, soft deletes, polymorphic audit logs
- Form Requests for validation (reused in API and Blade)
- **Currency**: VND for all bookings, payments, coupons, payouts

## Environment & Setup Gotchas
- No `.env.example` in backend — copy `.env` manually and configure:
  - `DB_CONNECTION` (mysql/pgsql), `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
  - `REDIS_HOST`, `QUEUE_CONNECTION=redis` (required for async jobs)
  - `VNPAY_TMN_CODE`, `VNPAY_HASH_SECRET`, `VNPAY_URL`, `VNPAY_RETURN_URL`, `VNPAY_IPN_URL` (see `backend/PAYMENTS.md`)
  - `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME`
- Frontend: `.env.local` with `VITE_API_URL`, `VITE_REVERB_*` (already present)
- Redis required for queues; `QUEUE_CONNECTION=sync` only for local testing
- For local VNPay IPN testing: ngrok required (`VNPAY_IPN_URL=https://<ngrok>/api/v1/payments/vnpay-ipn`)

## Testing
- Backend: Pest (PHP) — Unit tests in `tests/Unit`, Feature in `tests/Feature`
- Frontend: Vitest — tests in `src/test/`, setup in `vitest.setup.ts`
- SQLite in-memory for backend tests (configured in `phpunit.xml`)
- Run `php artisan config:clear` before tests (in composer test script)

## Code Style
- Backend: Laravel Pint (run `./vendor/bin/pint` or `composer pint` if added)
- Frontend: ESLint flat config (`npm run lint`)

## Deployment Notes
- Set `APP_ENV=production`, `APP_DEBUG=false`
- Run migrations with `--force`
- Supervisor for queue worker, cron for scheduler, Supervisor for Reverb
- Frontend: static deploy (Vercel/Netlify/S3+CloudFront), API URL in env
- VNPay credentials for production (not sandbox)

## Files to Reference
- `README.md` — full project docs
- `backend/app/README.md` — folder conventions
- `backend/docs/INFRASTRUCTURE.md` — queue/scheduler/Reverb setup
- `backend/PAYMENTS.md` — VNPay flow, IPN, refunds (unified IPN for hotel + tour)
- `composer.json` scripts: `setup`, `dev`, `test`