# Backend (Travel-Booking)

Backend Laravel 12 của nền tảng đặt phòng khách sạn và tour du lịch. Ứng dụng cung cấp:

- REST API cho ứng dụng khách hàng React tại `/api/v1`.
- Cổng Blade (session auth) cho admin, vendor và guide tại `/admin`.
- Thanh toán VNPay, hoàn tiền, payout vendor và hoa hồng nền tảng.
- Chat tour realtime qua Laravel Reverb, xử lý nền bằng queue và scheduler.

Xem [README gốc](../README.md) để có bức tranh tổng thể và [app/README.md](app/README.md) cho quy ước Clean Architecture.

## Tech stack

- PHP 8.2+, Laravel 12
- Laravel Sanctum 4 (token API), session auth (Blade)
- spatie/laravel-permission 6
- Laravel Reverb 1 (broadcast), Pusher protocol
- VNPay (thanh toán, IPN, Merchant Web API refund)
- Google Gemini (`App\Services\GeminiClient`) cho AI
- Geoapify cho autocomplete địa chỉ
- MySQL 8 (production), SQLite in-memory (test mặc định), Redis (queue/cache khuyến nghị)
- Pest 4, PHPUnit, Laravel Pint

## Yêu cầu

- PHP 8.2+ với các extension Laravel/MySQL cần thiết
- Composer
- Node.js `^20.19.0` hoặc `>=22.12.0` nếu build asset admin
- MySQL 8
- Redis (khuyến nghị cho queue/cache; local có thể dùng `sync` hoặc `database`)

## Cài đặt

Repo không có `.env.example`; cấu hình trực tiếp `backend/.env`.

```bash
composer install
php artisan key:generate
php artisan migrate --seed
php artisan storage:link

npm install
npm run build
```

Chạy API và các tiến trình nền:

```bash
php artisan serve           # http://localhost:8000
php artisan queue:work      # job nền
php artisan schedule:work   # scheduler (dev)
php artisan reverb:start    # WebSocket server
```

`composer dev` chạy đồng thời `artisan serve`, queue listener, log viewer và Vite admin. Lệnh này **không** chạy scheduler và Reverb, cần khởi động riêng khi phát triển chat tour.

Health check: `GET /up`.

### Asset admin

Giao diện admin dùng theme Bootstrap 5 trong `public/admin/dist`. Một số layout dùng `@vite`, vì vậy cần cài npm và build:

```bash
npm install
npm run build
```

Khi phát triển giao diện, chạy thêm `npm run dev`.

## Cấu hình môi trường

Các nhóm biến chính trong `.env`:

```env
APP_URL=http://localhost:8000
DB_CONNECTION=mysql
DB_DATABASE=travel_booking
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
BROADCAST_CONNECTION=reverb

VNPAY_TMN_CODE=
VNPAY_HASH_SECRET=
VNPAY_URL=https://sandbox.vnpayment.vn/paymentv2/vpcpay.html
VNPAY_RETURN_URL=http://localhost:5173/vnpay-return
VNPAY_IPN_URL=https://<public-host>/api/v1/payments/vnpay-ipn
VNPAY_API_URL=https://sandbox.vnpayment.vn/merchant_webapi/api/transaction
VNPAY_REFUND_ENABLED=false
VNPAY_REFUND_MAX_ATTEMPTS=5

REVERB_APP_ID=
REVERB_APP_KEY=
REVERB_APP_SECRET=
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http

GEMINI_API_KEY=
GEOAPIFY_API_KEY=
```

- `VNPAY_REFUND_ENABLED=false` giữ hoàn tiền ở mức ghi nhận nội bộ, không gọi VNPay.
- Khi test IPN local cần URL công khai (ngrok/cloudflared) cho `VNPAY_IPN_URL`.
- Frontend đọc các biến `VITE_REVERB_*` từ `frontend/.env.local`.

## Cổng truy cập và xác thực

API chỉ cho tài khoản `customer` đăng nhập qua `/api/v1/login` (Sanctum token). Admin, vendor và guide đăng nhập bằng session tại các cổng Blade:

| Khu vực | Đăng nhập | Dashboard |
|---------|-----------|-----------|
| Super Admin / Admin | `/login/admin` | `/admin/dashboard` |
| Vendor | `/login` | `/admin/vendor/dashboard` |
| Guide | `/login/guide` | `/admin/vendor/dashboard` |
| Chọn vai trò | `/login/choose` | — |
| Đăng ký vendor | `/register/vendor` | — |
| Đăng ký guide | `/register/guide` | — |

- Route gốc `/` redirect tới dashboard theo vai trò nếu đã đăng nhập, ngược lại tới `/login/choose`.
- Vendor/guide dùng chung tiền tố `/admin/vendor` nhưng middleware tách quyền: vendor quản lý khách sạn; guide quản lý tour/chat. Guide không có quyền quản lý khách sạn và ngược lại.
- Vendor phải được admin duyệt (`vendor.approved`) trước khi quản lý khách sạn/phòng.
- API dùng middleware `auth:sanctum`, `customer`, `auth.optional`; broadcast auth cho React ở `POST /api/v1/broadcasting/auth`.

Vai trò: `super_admin`, `admin`, `vendor`, `guide`, `customer` (enum `App\Enums\Role`, đồng bộ với Spatie roles).

## Cấu trúc route

| File | Nội dung |
|------|----------|
| `routes/api.php` | REST API `/api/v1` cho khách hàng và IPN VNPay |
| `routes/admin.php` | Admin, super admin và vendor/guide dashboard |
| `routes/auth.php` | Đăng nhập/đăng ký/xác thực email/quên mật khẩu |
| `routes/web.php` | Redirect gốc, profile, dashboard mặc định |
| `routes/channels.php` | Kênh broadcast chat tour |
| `routes/console.php` | Lịch chạy commands |

Nhóm API tiêu biểu:

- Auth: `POST /login`, `POST /register`, `POST /register/vendor`, `GET /me`, `PUT /me`, `POST /logout`
- Khách sạn: `GET /hotels`, `GET /hotels/{id}`, `GET /hotels/{id}/rooms/availability`
- Booking khách sạn: `POST /bookings/preview`, `POST /bookings/{uuid}/checkout-session`, `POST /bookings/{uuid}/cancel`, `GET /bookings/{uuid}/invoice`
- Tour: `GET /tours`, `GET /tours/{uuid}`, `GET /tours/{uuid}/availability`, `GET /tour-providers/{uuid}`
- Booking tour: `POST /tour-bookings/preview`, `POST /tour-bookings/{uuid}/checkout-session`, `POST /tour-bookings/{uuid}/cancel`
- Chat tour: `GET/POST /tour-bookings/{uuid}/messages`, `POST /tour-bookings/{uuid}/messages/read`
- Review: `GET/POST /reviews`, `GET/POST /tour-reviews`
- Yêu thích: `/wishlist`, `/saved-tours`, `/saved-guides`
- Hỗ trợ: `/support-tickets`
- AI: `POST /ai/chat`, `GET /ai/recommendations`, `GET /hotels/{id}/review-sentiment`
- VNPay: `GET /payments/vnpay-ipn`, alias `GET /payments/tour-vnpay-ipn`

## Thanh toán VNPay

- Một IPN URL dùng chung cho hotel và tour. `UnifiedVnpayIpnController` phân loại giao dịch dựa trên prefix `tour_` của `vnp_TxnRef`.
- Chữ ký được xác thực; giao dịch ghi vào `webhook_events` để chống trùng.
- Xử lý xác nhận chạy qua queue job (`ProcessVnpayIpn`, `ProcessTourIpn`).
- Tour dùng outbox (`outbox_events`) và consumer idempotent (`event_consumption_receipts`).
- Hoàn tiền dùng `payment_refunds` + `ProcessVnpayRefund`; retry/lease do bản ghi refund quản lý, không phụ thuộc số lần retry của queue.
- Booking khách sạn: `pending_payment` → `confirmed` → `completed`/`cancelled`.
- Tour: `pending_payment` → `confirmed` → `ongoing` → `completed`/`cancelled`/`disputed`/`refunded`.
- Tiền tệ: VND.

Chi tiết đầy đủ: [PAYMENTS.md](PAYMENTS.md).

## Queue, scheduler và realtime

### Queue

Các tác vụ nền gồm email xác nhận booking, IPN, refund và broadcast; publish outbox chạy qua scheduler. Production khuyến nghị:

```env
QUEUE_CONNECTION=redis
```

Chạy worker bằng Supervisor:

```bash
php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
```

### Scheduler

Chạy cron mỗi phút:

```bash
* * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1
```

Các command định kỳ trong `routes/console.php`:

- `tour-bookings:expire-pending` — hủy booking tour quá hạn thanh toán
- `tour:expire-holds` — giải phóng slot đang giữ
- `outbox:publish` — publish sự kiện thanh toán tour
- `refunds:process` — xử lý refund đến hạn

Replay outbox thủ công:

```bash
php artisan outbox:replay {event_id}
php artisan outbox:replay --all-failed
```

### Reverb

Tour chat phát event `TourMessageSent`, `TourMessageInboxUpdated`, `TourMessageRead`. Chạy:

```bash
php artisan reverb:start --host=0.0.0.0 --port=8080
```

Frontend kết nối Reverb bằng `VITE_REVERB_*` và fallback sang polling nếu socket không khả dụng. Chi tiết Supervisor/cron: [docs/INFRASTRUCTURE.md](docs/INFRASTRUCTURE.md).

## Cơ sở dữ liệu và quy ước

- Khóa chính số nguyên; UUID công khai cho users, bookings, tour bookings, reviews, tour products/providers.
- Soft deletes trên các model chính: `Booking`, `Hotel`, `Room`, `RoomType`, `TourBooking`, `TourProduct`, `TourProvider`.
- Dữ liệu vendor cô lập theo `hotel_id`/`provider_id`; kiểm tra quyền bằng middleware và policy.
- Giá, thuế, coupon, payment, payout đều dùng VND.
- Không có audit log đa hình.
- Trạng thái dùng enum trong `app/Enums`:
  - `BookingStatus`, `PaymentStatus`, `RefundStatus`, `RefundType`
  - `TourBookingStatus`, `TourPricingMode`, `TourSlotStatus`
  - `Role`, `TicketCategory`

Seed dữ liệu demo bằng `php artisan migrate --seed`. Mật khẩu chung `12345678`:

| Vai trò | Tài khoản |
|---------|-----------|
| Super Admin | `admin@test.com` |
| Admin | `admin2@test.com` |
| Vendor | `vendor@test.com`, `vendor2..6@test.com` |
| Guide | `guide-hg-01@test.local` … `guide-hg-10@test.local` |
| Customer | `customer@test.com` |

## Testing

Mặc định dùng SQLite in-memory (`phpunit.xml`), queue sync, mail array:

```bash
php artisan test
php artisan test --filter=TestName
php artisan test --coverage
```

Bộ integration trên MySQL dùng database `travel_booking_test` (`phpunit.mysql.xml`):

```bash
php artisan test -c phpunit.mysql.xml
```

Cấu hình database test trong `phpunit.mysql.xml` (mặc định `root`, password rỗng) trước khi chạy.

## Code style

```bash
./vendor/bin/pint
```

Quy ước chi tiết theo thư mục: [app/README.md](app/README.md).

## Tài liệu

- [../README.md](../README.md) — tổng quan dự án
- [../frontend/README.md](../frontend/README.md) — ứng dụng khách hàng
- [PAYMENTS.md](PAYMENTS.md) — VNPay, IPN, refund
- [docs/INFRASTRUCTURE.md](docs/INFRASTRUCTURE.md) — queue, scheduler, Reverb, outbox
- [docs/WEBSITE-SETTINGS.md](docs/WEBSITE-SETTINGS.md) — website settings
- [docs/GEOAPIFY-SETUP.md](docs/GEOAPIFY-SETUP.md) — autocomplete địa chỉ
- [../docs/plans/deploy-production-vps-2026-10-16.md](../docs/plans/deploy-production-vps-2026-10-16.md) — kế hoạch deploy VPS

## Triển khai production

- `APP_ENV=production`, `APP_DEBUG=false`
- `php artisan migrate --force`, `php artisan storage:link`, `php artisan optimize`
- Build asset admin và cache config/route/view
- Supervisor cho `queue:work` và `reverb:start`
- Cron `schedule:run` mỗi phút
- MySQL 8, Redis, backup DB và `storage/app/public`
- Cấu hình VNPay production và IPN URL công khai
