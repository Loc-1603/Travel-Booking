# Nền tảng đặt phòng khách sạn & du lịch

Một hệ thống đặt phòng khách sạn và tour du lịch **sẵn sàng sản xuất, cấp doanh nghiệp** được xây dựng với Laravel, Blade và React. Nền tảng cho phép các khách sạn và nhà cung cấp tour quản lý tài sản và đặt chỗ trong khi cung cấp cho khách hàng trải nghiệm đặt chỗ liền mạch.

## 🎯 Tổng quan

Travel-Booking là giải pháp SaaS hoàn chỉnh để quản lý đặt phòng khách sạn và tour du lịch ở quy mô lớn. Hỗ trợ đa khách sạn (vendors), đa tour provider, định giá động, thanh toán tích hợp và các bảng điều khiển quản trị toàn diện — tất cả được xây dựng theo nguyên tắc Clean Architecture và công nghệ web hiện đại.

**Xây dựng như thể khách sạn và khách hàng thực sẽ sử dụng nó vào ngày mai — không cắt góp, không logic đồ chơi.**

## ✨ Tính năng chính

### Nền tảng & Vận hành
- **Quản lý đa khách sạn & đa tour** — Vendors độc lập với cô lập hoàn toàn và thương hiệu tùy chỉnh; tour provider riêng biệt cho mỗi vendor
- **Quản lý phòng & tour động** — Tạo, cập nhật nhiều loại phòng/tour mỗi khách sạn/provider
- **Tính khả dụng & giá thông minh** — Theo dõi khả dụng thời gian thực với quy tắc giá động, slot tour, mùa vụ
- **Thanh toán tích hợp** — VNPay và PayPal với webhook và xác nhận bất đồng bộ
- **Hệ thống hoa hồng** — Tính toán và theo dõi hoa hồng tự động cho khách sạn và tour
- **Thanh toán nhà cung cấp** — Super Admin tạo payout theo kỳ, đánh dấu đã thanh toán kèm tham chiếu, xuất CSV; vendor xem lịch sử payout
- **Cài đặt website động** — Tên site, logo, favicon trong admin, vendor dashboard và frontend khách hàng
- **Phân quyền theo vai trò** — Quản lý khách sạn, quản trị nền tảng, khách hàng
- **Redis Queues** — Xử lý bất đồng bộ email, webhook, background jobs
- **Thiết kế API-First** — RESTful API cho khách hàng và admin
- **Coupon, Thuế, Chính sách hủy** — Áp dụng mã giảm giá, thuế theo khu vực, chính sách hủy phòng/tour
- **Hỗ trợ khách hàng** — Hệ thống support tickets với phản hồi hai chiều
- **Tranh chấp đặt chỗ** — Quản lý dispute cho booking khách sạn và tour
- **AI hỗ trợ** — Chat AI trên site, đề xuất cá nhân hóa, phân tích sentiment review

### Vendor Dashboard
- **Phân tích & báo cáo** — Biểu đồ doanh thu, booking theo trạng thái, top khách sạn/tour
- **Quản lý booking** — Toàn vòng đời với theo dõi trạng thái; đánh dấu booking cũ để dọn hiển thị; xem booking cũ riêng
- **Thông tin doanh nghiệp** — Nhiều tài khoản ngân hàng cho payout (người nhận, ngân hàng, routing, SWIFT, currency)
- **Lịch sử payout** — Xem payout quá khứ với kỳ, gross, commission, net, trạng thái
- **Hồ sơ provider/guide** — Quản lý hồ sơ tour provider, avatar, ngôn ngữ, trạng thái phê duyệt
- **Tin nhắn tour** — Hộp thư trao đổi giữa khách và provider

### Trải nghiệm khách hàng
- **Cổng khách hàng hiện đại** — Tìm kiếm khách sạn và tour, lọc, bản đồ
- **Wishlist & Lưu** — Lưu khách sạn và tour yêu thích
- **Quản lý đặt chỗ** — Toàn vòng đời, hoàn tiền, dispute
- **Support trực tiếp** — Tạo ticket hỗ trợ, theo dõi phản hồi
- **Thương hiệu động** — Tên site, logo, favicon từ cài đặt website trên mọi trang

## 🏗️ Kiến trúc

Dự án tuân thủ **Clean Architecture** với tách biệt mối quan tâm rõ ràng:

```
backend/
├── app/
│   ├── Actions/          # Thao tác một mục đích, tái sử dụng
│   ├── DTOs/             # Data Transfer Objects kiểu an toàn
│   ├── Enums/            # Enum miền (BookingStatus, PaymentStatus, ...)
│   ├── Services/         # Logic nghiệp vụ cốt lõi
│   ├── Repositories/     # Lớp truy cập dữ liệu (interface + Eloquent)
│   ├── Models/           # Eloquent models (không logic nghiệp vụ)
│   ├── Http/Controllers/ # Lớp điều phối mỏng
│   ├── Http/Requests/    # Validation Form Request
│   ├── Http/Resources/   # Định dạng phản hồi API
│   └── Policies/         # Quy tắc ủy quyền
frontend/
├── src/
│   ├── components/       # Component React tái sử dụng
│   ├── pages/            # Trang
│   ├── hooks/            # Custom hooks
│   └── services/         # Client API
```

## 🛠️ Tech Stack

### Backend
- **Framework:** Laravel 12
- **Authentication:** Laravel Sanctum (token API)
- **Database:** MySQL 8.0+ / PostgreSQL 13+
- **Cache & Queues:** Redis
- **Payments:** VNPay & PayPal SDK
- **Task Scheduling:** Laravel Scheduler & Queue Workers

### Admin Dashboards
- **Template:** Blade components
- **UI:** Tailwind CSS
- **Charts:** Chart.js
- **Layout:** Responsive sidebar

### Frontend Khách hàng
- **Framework:** React 19
- **Build:** Vite 7
- **Styling:** Tailwind CSS 4
- **State & Data:** React Query
- **HTTP:** Axios
- **Routing:** React Router v7
- **i18n:** i18next
- **Bản đồ:** Leaflet / React-Leaflet

## 🚀 Bắt đầu

### Yêu cầu
- PHP 8.2+
- Composer
- Node.js 18+
- MySQL 8.0+ hoặc PostgreSQL 13+
- Redis

### Backend Setup
```bash
cd backend
composer install
cp .env.example .env   # nếu không có, copy thủ công từ .env hiện tại
php artisan key:generate
php artisan migrate
php artisan serve
php artisan queue:work
php artisan schedule:work
```
API Base URL: `http://localhost:8000/api/v1`

### Frontend Setup
```bash
cd frontend
npm install
npm run dev
```
Frontend URL: `http://localhost:5173`

Vite proxy `/api` → backend. Hoặc tạo `.env.local` với `VITE_API_URL=http://localhost:8000/api/v1`

### Admin Dashboard
- Super Admin: `http://localhost:8000/admin`
- Vendor: `http://localhost:8000/admin/vendor`

## 📚 Tài liệu triển khai

- `backend/app/README.md` — Quy ước thư mục
- `backend/docs/INFRASTRUCTURE.md` — Queue/Scheduler
- `backend/PAYMENTS.md` — Luồng VNPay, IPN, hoàn tiền

## 🔑 Thực thể cốt lõi

### Khách sạn & Phòng
- Hồ sơ khách sạn, liên hệ, thành phố/quốc gia
- Nhiều loại phòng, tiện nghi, giá cơ sở, quy tắc chiếm chỗ
- Lịch khả dụng, hình ảnh

### Đặt phòng khách sạn
- Vòng đời pending → confirmed → completed/cancelled
- Chi tiết khách, nhiều phòng, theo dõi thanh toán

### Tour & Provider
- Tour provider / guide với bio, ngôn ngữ, avatar
- Tour products, điểm đến, tỉnh, hình ảnh
- Slot khả dụng theo ngày, giá động
- Đặt tour, tin nhắn, review

### Thanh toán
- Phương thức VNPay/PayPal, ghi nhận giao dịch, hoàn tiền
- Tính hoa hồng, payout vendor với trạng thái Pending → Processing → Paid

### Cài đặt website
- Tên, mô tả, logo, favicon
- Thông tin liên hệ, mạng xã hội, meta tags

### Coupon, Thuế, Hỗ trợ
- Mã giảm giá với giới hạn sử dụng
- Thuế theo khu vực, thuế bao gồm
- Support tickets với category và replies
- Booking disputes

## 🔐 Xác thực & Ủy quyền

- Guest Users: truy cập công khai
- Registered Customers: lịch sử đặt chỗ, review
- Hotel Managers / Tour Providers: quản lý tài sản riêng
- Platform Admins: quản trị toàn nền tảng

Sử dụng **Laravel Sanctum** cho API token và **Laravel Policies** cho ủy quyền.

## 💳 Tích hợp thanh toán

- VNPay & PayPal cho thanh toán an toàn
- Webhook xử lý xác nhận thanh toán
- Xác nhận bất đồng bộ với xác thực webhook
- Xử lý hoàn tiền
- Theo dõi hoa hồng và payout

## 🔄 Xử lý bất đồng bộ

Jobs Redis cho:
- Gửi email xác nhận đặt chỗ
- Xử lý webhook thanh toán
- Tính hoa hồng
- Tổng hợp phân tích

## 📊 Điểm nhấn Schema DB

- Multi-tenancy qua `hotel_id` / `vendor_id`
- UUID primary keys, soft deletes
- Quan hệ polymorphic cho audit log
- Timestamp cho audit trail

## 🧪 Testing

```bash
php artisan test
php artisan test --coverage
php artisan test --filter TestName
```

## 📦 Triển khai

Checklist production:
- `APP_ENV=production`, `APP_DEBUG=false`
- Chạy migration với `--force`
- Redis cho cache & queue
- Cấu hình VNPay/PayPal
- Supervisor cho queue worker
- Cron cho scheduler
- SSL/TLS, CORS, backup DB

## 🎓 Quy tắc tuyệt đối

1. **Clean Architecture** — Logic nghiệp vụ không trong controller
2. **Cô lập multi-vendor** — Không rò rỉ dữ liệu
3. **Bảo mật trước** — Validate input, escape output, CSRF, rate limit
4. **Khả mở rộng** — Async, cache, tối ưu DB, API stateless
5. **Khả kiểm thử** — Unit test services/actions, integration test workflow
6. **Tài liệu** — Code tự giải thích, naming rõ ràng, PHPDoc

## 🤝 Đóng góp

1. Fork repo
2. Tạo feature branch
3. Commit thay đổi
4. Push branch
5. Mở Pull Request

## 📄 License

MIT License

## 👤 Tác giả

Created by Ho Xuan Loc

## 📞 Hỗ trợ

- Mở issue trên GitHub
- Xem tài liệu trong thư mục gốc
- Tham khảo Laravel/React docs

## 🗺️ Roadmap

- [x] Hệ thống đặt phòng khách sạn
- [x] Hỗ trợ đa khách sạn
- [x] Tích hợp thanh toán
- [x] Payout vendor (tạo, đánh dấu đã trả, xuất CSV)
- [x] Tài khoản ngân hàng vendor
- [x] Đánh dấu booking cũ
- [x] Cài đặt website động
- [x] Quản lý tour & tour provider
- [x] Đặt tour, slot khả dụng, tin nhắn tour
- [x] Wishlist & Saved hotels/tours
- [x] Support tickets
- [x] Coupon, thuế, chính sách hủy
- [x] AI chat & review sentiment
- [ ] Phân tích nâng cao
- [ ] Ứng dụng di động
- [ ] Gợi ý AI nâng cao
- [ ] Đa ngôn ngữ
- [ ] Báo cáo nâng cao
