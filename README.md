# Travel-Booking

Nền tảng đặt phòng khách sạn và đặt tour du lịch theo mô hình đa nhà cung cấp (multi-vendor), gồm REST API + cổng quản trị Blade cho admin/vendor/guide và ứng dụng React cho khách hàng.

- **Backend:** Laravel 12, PHP 8.2+, MySQL 8, Sanctum, Reverb, VNPay.
- **Frontend khách hàng:** React 19, Vite 7, Tailwind CSS 4, React Query.
- **Tiền tệ:** VND cho toàn bộ booking, payment, coupon và payout.
- **Ngôn ngữ:** giao diện khách hàng song ngữ Anh/Việt; admin/vendor/guide dùng tiếng Việt (admin có thêm tiếng Anh).

## 1. Tổng quan

Travel-Booking xử lý hai luồng nghiệp vụ chính:

1. **Khách sạn:** vendor quản lý khách sạn/phòng, tồn kho theo ngày, giá và booking; khách tìm kiếm, đặt phòng và thanh toán VNPay.
2. **Tour 1:1:** guide (tour provider) có hồ sơ riêng, tour theo giờ/ngày, slot khả dụng và chat realtime với khách sau khi thanh toán.

Nền tảng có phân quyền tách biệt cho `super_admin`, `admin`, `vendor`, `guide`, `customer`; dữ liệu vendor được cô lập theo `hotel_id`/`provider_id` và kiểm tra quyền ở policy/middleware.

## 2. Tính năng

### Khách hàng (React SPA)

- Tìm kiếm khách sạn theo điểm đến, ngày nhận/trả phòng, số khách; lọc, sắp xếp, phân trang và xem trên bản đồ.
- Chi tiết khách sạn, loại phòng, tồn kho theo khoảng ngày, giá theo ngày, phụ thu nhận/trả phòng muộn, thuế và chính sách hủy.
- Đặt phòng: xem trước giá, áp coupon, tạo booking, thanh toán VNPay, xem hóa đơn, hủy booking và gửi khiếu nại.
- Tour: danh sách theo tỉnh, hồ sơ guide, chi tiết tour, lịch slot theo giờ/ngày, giá theo giờ hoặc ngày, phí di chuyển, coupon, thuế, ngày blackout.
- Đặt tour: wizard chọn slot/số khách, xem trước giá, thanh toán VNPay, hủy, khiếu nại và đánh giá khi booking đã xác nhận/đang diễn ra/hoàn thành.
- Chat tour realtime qua Laravel Reverb sau khi thanh toán được xác nhận; tự động fallback sang polling khi socket không khả dụng.
- Tài khoản khách hàng: đăng ký/đăng nhập bằng Sanctum token, xác thực email, cập nhật hồ sơ, xem booking khách sạn/tour, hóa đơn.
- Yêu thích: lưu khách sạn, tour và guide.
- Đánh giá khách sạn và tour: hiển thị ngay sau khi gửi; admin có thể ẩn nội dung vi phạm (kiểm duyệt hậu kiểm).
- Hỗ trợ: tạo ticket và xem phản hồi từ admin.
- AI (Google Gemini): chat tư vấn (cần `GEMINI_API_KEY`), gợi ý khách sạn và phân tích cảm xúc review (hai tính năng sau có fallback khi chưa cấu hình key).
- Giao diện song ngữ Anh/Việt; tên site, logo, favicon, thông tin liên hệ và SEO lấy từ website settings.

### Vendor khách sạn (Blade)

- Đăng ký vendor, xác thực email, chờ admin duyệt hồ sơ và giấy tờ doanh nghiệp.
- Quản lý hồ sơ doanh nghiệp và nhiều tài khoản ngân hàng nhận payout.
- Quản lý khách sạn, loại phòng, ảnh (sắp xếp lại), tồn kho và giá ghi đè theo ngày.
- Quản lý booking: xem chi tiết, đánh dấu hoàn thành, đánh dấu booking cũ, xem hóa đơn.
- Báo cáo doanh thu/booking và xuất CSV.
- Xem lịch sử payout (gross, commission, net, trạng thái, kỳ thanh toán) và gửi support ticket.

### Guide / tour provider (Blade)

- Đăng ký guide, xác thực email, admin duyệt hồ sơ `TourProvider`.
- Quản lý hồ sơ guide: tiểu sử, ngôn ngữ, avatar/ảnh nội dung và ngày blackout.
- Xem tour booking, hóa đơn và hộp thư chat với khách.
- Dữ liệu tour product và lịch slot được nạp qua seeder; guide vận hành booking và trao đổi với khách.

### Admin / Super Admin (Blade)

- Dashboard thống kê booking theo trạng thái, xu hướng; doanh thu và top vendor dành cho super admin (Chart.js).
- Quản lý người dùng, role và permission.
- Duyệt/từ chối vendor và guide; tải tài liệu doanh nghiệp.
- Quản lý hoa hồng nền tảng, tạo payout theo kỳ, đánh dấu đã trả và xuất CSV.
- Cài đặt website: tên, mô tả, logo, favicon, liên hệ, mạng xã hội, SEO.
- Quản lý quốc gia, thành phố, tiện nghi; tỉnh/thành và điểm tham quan tour.
- Kiểm duyệt review, xử lý support ticket và dispute của booking khách sạn/tour.
- Tìm kiếm/autocomplete địa chỉ bằng Geoapify.

### Thanh toán & vận hành

- **VNPay** là cổng thanh toán duy nhất đang hoạt động cho khách sạn và tour (PayPal chỉ là placeholder disabled "sắp ra mắt" ở checkout tour; không có Stripe/MoMo).
- Một IPN endpoint dùng chung: `GET /api/v1/payments/vnpay-ipn`; handler phân loại giao dịch tour qua prefix `tour_` trong `vnp_TxnRef`. Endpoint alias cũ `/api/v1/payments/tour-vnpay-ipn` vẫn được giữ.
- IPN có kiểm tra chữ ký, lưu `webhook_events` để chống xử lý trùng và xử lý nền qua queue job.
- Xác nhận thanh toán tour dùng **outbox pattern** (`outbox_events`) và consumer idempotent (`event_consumption_receipts`).
- Hoàn tiền VNPay qua Merchant Web API, ledger `payment_refunds`, retry có kiểm soát; bật bằng `VNPAY_REFUND_ENABLED=true`.
- Hoa hồng được tính theo cấu hình nền tảng; payout vendor hỗ trợ trạng thái pending → processing → paid.
- Scheduler tự động hết hạn booking tour chờ thanh toán, giải phóng slot giữ chỗ, publish outbox và xử lý refund.
- Queue xử lý email/notification, IPN, refund và broadcast; khuyến nghị Redis ở production.
- Reverb cung cấp realtime cho chat tour qua các event `TourMessageSent`, `TourMessageInboxUpdated`, `TourMessageRead`.

## 3. Kiến trúc

```text
backend/
├── app/
│   ├── Actions/              # Thao tác một mục đích
│   ├── Console/              # Artisan commands
│   ├── DTOs/                 # Data Transfer Objects
│   ├── Enums/                # Trạng thái/vai trò miền
│   ├── Events/               # Domain events
│   ├── Exceptions/
│   ├── Helpers/              # Hàm dùng chung (autoload)
│   ├── Http/
│   │   ├── Controllers/      # Api/V1, Admin, Admin/Vendor, Auth
│   │   ├── Middleware/
│   │   ├── Requests/
│   │   └── Resources/
│   ├── Jobs/                 # Queue jobs
│   ├── Listeners/            # Event listeners
│   ├── Mail/                 # Mailable
│   ├── Models/               # Eloquent models
│   ├── Notifications/
│   ├── Policies/
│   ├── Providers/
│   ├── Repositories/
│   │   ├── Contracts/        # Interface
│   │   └── Eloquent/         # Triển khai Eloquent
│   ├── Services/             # Nghiệp vụ cốt lõi
│   ├── Support/
│   ├── Traits/
│   └── View/Components/
├── routes/                   # api, admin, auth, web, channels, console
├── resources/views/          # Blade admin/vendor/guide/auth
├── tests/                    # Unit, Feature, Integration
└── docs/                     # Hạ tầng, website settings, Geoapify
frontend/
├── src/
│   ├── components/           # UI tái sử dụng
│   ├── contexts/             # Auth, language, toast, website settings
│   ├── hooks/                # Wishlist, chat, countdown, ...
│   ├── i18n/                 # en.json, vi.json
│   ├── lib/                  # API client, Echo, search helpers
│   ├── pages/                # Trang khách hàng
│   ├── styles/
│   └── test/
├── vite.config.js            # Proxy /api -> backend
└── vitest.config.ts
```

**Nguyên tắc:** controller chỉ điều phối; nghiệp vụ nằm trong `Services`/`Actions`; truy vấn nghiệp vụ đi qua `Repositories` (một số dashboard/report dùng query builder trực tiếp); validation dùng Form Requests; authorization dùng Policies và middleware vai trò. Khóa chính là số nguyên, các đối tượng công khai (users, bookings, tour bookings, reviews, tour products/providers) dùng UUID; các model chính có soft deletes. Không có audit log đa hình.

## 4. Tech stack

| Thành phần | Công nghệ |
|------------|-----------|
| Backend | Laravel 12, PHP 8.2+, Sanctum 4, spatie/laravel-permission 6 |
| Database | MySQL 8 (production); SQLite in-memory (test mặc định) |
| Queue / Cache | Redis (khuyến nghị); `sync`/`database` cho local |
| Realtime | Laravel Reverb 1.x + Laravel Echo + Pusher protocol |
| Thanh toán | VNPay (payment, IPN, refund) |
| AI | Google Gemini (`GEMINI_API_KEY`) |
| Địa chỉ | Geoapify Geocoding/Autocomplete |
| Admin UI | Blade + Bootstrap 5 admin theme + Chart.js 4 (CDN) |
| Frontend | React 19, Vite 7, Tailwind CSS 4, React Query 5, Axios |
| Routing / i18n | React Router 7, i18next |
| Bản đồ | Leaflet + React-Leaflet |
| Test | Pest 4 + PHPUnit; Vitest 5 + Testing Library + axe-core |
| Code style | Laravel Pint, ESLint |

## 5. Cài đặt

### Yêu cầu

- PHP 8.2+ và Composer
- Node.js `^20.19.0` hoặc `>=22.12.0` (ràng buộc của Vite 7)
- MySQL 8 cho môi trường thật; Redis cho queue/cache/broadcast ở production
- Extension PHP theo yêu cầu của Laravel và MySQL

### Backend

```bash
cd backend
composer install

# Repo không có .env.example: cấu hình trực tiếp backend/.env
php artisan key:generate

php artisan migrate --seed
php artisan storage:link

# Asset admin dùng Vite
npm install
npm run build

php artisan serve
```

Các tiến trình cần chạy song song khi phát triển:

```bash
php artisan queue:work      # job nền (email, IPN, refund, broadcast)
php artisan schedule:work   # scheduler
php artisan reverb:start    # chat realtime
```

Hoặc `composer dev` để chạy server + queue listener + log + Vite admin; lệnh này **không** khởi động scheduler và Reverb.

- API base: `http://localhost:8000/api/v1`
- Health check: `http://localhost:8000/up`

Cấu hình tối thiểu trong `backend/.env`:

- `APP_URL`, `DB_CONNECTION`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `QUEUE_CONNECTION`, `REDIS_HOST` (production)
- `VNPAY_TMN_CODE`, `VNPAY_HASH_SECRET`, `VNPAY_URL`, `VNPAY_RETURN_URL`, `VNPAY_IPN_URL`
- `VNPAY_REFUND_ENABLED`, `VNPAY_API_URL` nếu dùng hoàn tiền
- `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME`
- `GEMINI_API_KEY` (tùy chọn), `GEOAPIFY_API_KEY`
- Khi test IPN local cần URL công khai (ví dụ ngrok) cho `VNPAY_IPN_URL`

### Frontend

```bash
cd frontend
npm install
npm run dev
```

- URL: `http://localhost:5173`
- Vite proxy `/api` → `http://127.0.0.1:8000`
- `.env.local` trong thư mục `frontend` (không commit) hỗ trợ:
  - `VITE_API_URL=http://localhost:8000/api/v1`
  - `VITE_REVERB_APP_KEY`, `VITE_REVERB_HOST`, `VITE_REVERB_PORT`, `VITE_REVERB_SCHEME`

### Tài khoản seed

Sau `php artisan migrate --seed`, mật khẩu chung là `12345678`:

| Vai trò | Email | Trang đăng nhập |
|---------|-------|-----------------|
| Super Admin | `admin@test.com` | `/login/admin` |
| Admin | `admin2@test.com` | `/login/admin` |
| Vendor | `vendor@test.com` (+ `vendor2..6@test.com`) | `/login` |
| Guide | `guide-hg-01@test.local` … `guide-hg-10@test.local` | `/login/guide` |
| Customer | `customer@test.com` | React `/login` |

## 6. Cổng truy cập

| Khu vực | URL |
|---------|-----|
| Chọn vai trò đăng nhập | `/login/choose` |
| Admin/Super Admin | `/login/admin` → `/admin/dashboard` |
| Vendor | `/login` → `/admin/vendor/dashboard` |
| Guide | `/login/guide` → `/admin/vendor/dashboard` |
| Đăng ký vendor | `/register/vendor` |
| Đăng ký guide | `/register/guide` |
| Ứng dụng khách hàng | `http://localhost:5173` |
| VNPay return | `http://localhost:5173/vnpay-return` |

Vendor và guide dùng chung khung `/admin/vendor/*`; middleware tách quyền quản lý khách sạn (vendor) và tour/chat (guide).

## 7. API chính

Tất cả endpoint khách hàng nằm dưới `/api/v1` và dùng Sanctum Bearer token (trừ endpoint công khai).

| Nhóm | Endpoint tiêu biểu |
|------|--------------------|
| Auth | `POST /login`, `POST /register`, `GET /me`, `PUT /me`, `POST /logout` |
| Khách sạn | `GET /hotels`, `GET /hotels/{id}`, `GET /hotels/{id}/rooms/availability` |
| Booking khách sạn | `POST /bookings/preview`, `POST /bookings/{uuid}/checkout-session`, `POST /bookings/{uuid}/cancel`, `GET /bookings/{uuid}/invoice` |
| Tour | `GET /tours`, `GET /tours/{uuid}`, `GET /tours/{uuid}/availability`, `GET /tour-providers/{uuid}` |
| Booking tour | `POST /tour-bookings/preview`, `POST /tour-bookings/{uuid}/checkout-session`, `GET /tour-bookings/{uuid}/messages` |
| Chat tour | `GET/POST /tour-bookings/{uuid}/messages`, `POST /tour-bookings/{uuid}/messages/read` |
| Review | `GET /reviews`, `GET /tour-reviews`, `POST /reviews`, `POST /tour-reviews` |
| Yêu thích | `GET/POST /wishlist`, `/saved-tours`, `/saved-guides`; `DELETE` kèm `{hotelId}`/`{tourId}`/`{providerUuid}` |
| Hỗ trợ | `GET/POST /support-tickets`, `POST /support-tickets/{id}/replies` |
| AI | `POST /ai/chat`, `GET /ai/recommendations`, `GET /hotels/{id}/review-sentiment` |
| VNPay | `GET /payments/vnpay-ipn`, `GET /payments/tour-vnpay-ipn` |

## 8. Thanh toán

Luồng khách sạn:

1. `POST /bookings/{uuid}/checkout-session` tạo payment và URL thanh toán VNPay.
2. Khách thanh toán và được chuyển về `/vnpay-return`.
3. VNPay gọi IPN; backend xác thực chữ ký, ghi `webhook_events`, đưa job vào queue.
4. Job xác nhận payment/booking và phát event `PaymentConfirmed` để gửi email.

Luồng tour dùng outbox: IPN ghi event trong cùng transaction với payment; scheduler publish event; listener idempotent xác nhận tour booking.

Hoàn tiền: tạo `PaymentRefund`, job gọi VNPay Merchant Web API, cập nhật trạng thái và cho phép retry theo lease. Chi tiết xem [backend/PAYMENTS.md](backend/PAYMENTS.md).

## 9. Testing & chất lượng mã

```bash
# Backend (SQLite in-memory)
cd backend
php artisan test
php artisan test --filter=TestName
php artisan test --coverage

# Integration trên MySQL travel_booking_test
php artisan test -c phpunit.mysql.xml

./vendor/bin/pint
```

```bash
# Frontend
cd frontend
npm run test
npm run test:watch
npm run lint
npm run build
```

## 10. Triển khai

- `APP_ENV=production`, `APP_DEBUG=false`, cấu hình đúng `APP_URL`.
- Chạy `php artisan migrate --force`, `php artisan storage:link`, `php artisan optimize`.
- Build asset admin: `npm ci && npm run build` trong `backend`.
- Build frontend: `npm ci && npm run build` trong `frontend`, deploy thư mục `dist/`.
- Chạy Supervisor cho `queue:work` và `reverb:start`.
- Cron mỗi phút: `php artisan schedule:run`.
- Redis cho queue/cache; MySQL 8; backup DB và `storage/app/public`.
- Cấu hình VNPay production và URL IPN công khai.
- Đặt `VITE_API_URL` trỏ tới API production khi build frontend.

Hướng dẫn hạ tầng chi tiết: [backend/docs/INFRASTRUCTURE.md](backend/docs/INFRASTRUCTURE.md). 

## 11. Tài liệu

- [backend/README.md](backend/README.md) — cài đặt, route, vận hành backend.
- [backend/app/README.md](backend/app/README.md) — quy ước Clean Architecture.
- [frontend/README.md](frontend/README.md) — cấu trúc và route frontend.
- [backend/PAYMENTS.md](backend/PAYMENTS.md) — VNPay, IPN, hoàn tiền.
- [backend/docs/INFRASTRUCTURE.md](backend/docs/INFRASTRUCTURE.md) — queue, scheduler, Reverb, outbox.
- [backend/docs/WEBSITE-SETTINGS.md](backend/docs/WEBSITE-SETTINGS.md) — website settings.
- [backend/docs/GEOAPIFY-SETUP.md](backend/docs/GEOAPIFY-SETUP.md) — autocomplete địa chỉ.

## 12. Roadmap

- [x] Đặt phòng khách sạn, quản lý phòng và tồn kho theo ngày
- [x] Đa vendor, phân quyền admin/vendor/guide/customer
- [x] Thanh toán VNPay, IPN, hoàn tiền và invoice
- [x] Payout vendor, hoa hồng, báo cáo và xuất CSV
- [x] Đặt tour 1:1, slot, blackout, chat realtime
- [x] Review, support ticket, dispute
- [x] Wishlist khách sạn/tour/guide
- [x] Website settings và song ngữ Anh/Việt
- [x] AI chat, gợi ý khách sạn, phân tích cảm xúc review
- [ ] Ứng dụng di động
- [ ] Phân tích nâng cao và báo cáo tùy biến
- [ ] Quản lý tour product trực tiếp trên giao diện vendor/guide

## 13. Giấy phép

MIT License — tác giả: Ho Xuan Loc.
