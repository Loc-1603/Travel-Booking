# Backend Infrastructure

## Queue worker

Background jobs (emails, notifications, async payment confirmation) use Laravel queues.

- **Recommended:** Redis. Set in `.env`:
  - `QUEUE_CONNECTION=redis`
  - `REDIS_HOST=127.0.0.1`, `REDIS_PASSWORD=null`, `REDIS_PORT=6379`
- **Alternative:** Database driver: `QUEUE_CONNECTION=database` and run `php artisan queue:table` + migrate.

**Run the worker (development):**

```bash
php artisan queue:work
```

**Production (Supervisor example):**

```ini
[program:hotel-booking-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/backend/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
numprocs=1
user=www-data
redirect_stderr=true
stdout_logfile=/path/to/backend/storage/logs/worker.log
stopwaitsecs=3600
```

Replace `/path/to/backend` with the actual project path.

---

## Realtime (Reverb)

Tour chat uses Laravel Reverb for realtime messages. Events implementing `ShouldBroadcast`
(TourMessageSent, TourMessageInboxUpdated, TourMessageRead) go through the queue in
production, so the queue worker must be running; in dev `QUEUE_CONNECTION=sync` runs them
immediately.

**Run the socket server (development):**

```bash
php artisan reverb:start
```

**Broadcast auth endpoints:**
- Admin (session cookie): `POST /broadcasting/auth` (registered via `channels` in `bootstrap/app.php`).
- API (Bearer token, React SPA): `POST /api/v1/broadcasting/auth` (registered in `AppServiceProvider`).

**Production (Supervisor example):**

```ini
[program:hotel-booking-reverb]
command=php /path/to/backend/artisan reverb:start --host=0.0.0.0 --port=8080
autostart=true
autorestart=true
numprocs=1
user=www-data
redirect_stderr=true
stdout_logfile=/path/to/backend/storage/logs/reverb.log
```

---

## Scheduler

The Laravel scheduler runs scheduled tasks (daily reports, cleanup, availability batch jobs).

**Single cron entry (run every minute):**

```bash
* * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1
```

Replace `/path/to/backend` with the actual project path.

Define scheduled jobs in `routes/console.php` using `Schedule::...`.
