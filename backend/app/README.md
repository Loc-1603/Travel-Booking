# Cấu trúc thư mục `app/`

Tài liệu này mô tả quy ước tổ chức code backend của Travel-Booking (Laravel 12, Clean Architecture): controller chỉ điều phối, business logic nằm ở `Services`/`Actions`, và phần lớn truy vấn thuộc nghiệp vụ được thực hiện qua `Repositories`.

## Các lớp trong `app/`

| Thư mục | Vai trò |
|---------|---------|
| **Actions** | Thao tác một mục đích: tạo/hủy booking khách sạn và tour, tạo checkout VNPay, xác nhận thanh toán VNPay, mở khiếu nại, publish outbox event. |
| **Console/Commands** | Artisan command cho tác vụ định kỳ hoặc vận hành (hết hạn booking tour chờ thanh toán, giải phóng slot tạm giữ, publish/replay outbox, xử lý/retry/settle refund, import/link ảnh, tách role cho staff). |
| **DTOs** | Object truyền dữ liệu có kiểu rõ ràng giữa các lớp (input booking/checkout, chi tiết bảng giá, dữ liệu truy vấn/hoàn tiền VNPay và kết quả trả về). Không chứa business logic. |
| **Enums** | Giá trị miền: `Role`, `BookingStatus`, `PaymentStatus`, `RefundStatus`, `RefundType`, `TourBookingStatus`, `TourPricingMode`, `TourSlotStatus`, `TicketCategory`. |
| **Events** | Domain event như `PaymentConfirmed`, `TourPaymentConfirmed`, `BookingDisputeCreated`, `SupportTicketReplyCreated` và các event broadcast của chat tour. |
| **Exceptions** | Dành cho exception miền/HTTP; cấu hình xử lý exception toàn cục nằm ở `bootstrap/app.php`. |
| **Helpers** | Hàm helper dùng chung trong `helpers.php` (autoload qua `composer.json`). |
| **Http/Controllers/Api/V1** | REST API cho ứng dụng khách hàng React và handler IPN VNPay. |
| **Http/Controllers/Admin** | Controller Blade cho màn hình super admin/admin (vendor, user, role, payout, cài đặt website, kiểm duyệt). |
| **Http/Controllers/Admin/Vendor** | Controller Blade cho cổng vendor và guide (khách sạn, phòng, booking, báo cáo, payout, hồ sơ guide, chat tour). |
| **Http/Controllers/Auth** | Xác thực bằng session, đăng ký, xác thực email và đặt lại mật khẩu. |
| **Http/Middleware** | Bảo vệ theo vai trò/cổng (`admin`, `admin_only`, `super_admin`, `vendor`, `vendor.approved`, `role`, `customer`), Sanctum optional, middleware thiết lập locale. |
| **Http/Requests** | Form Request chia theo `Api`, `Admin`, `Auth` dùng cho validate và authorize. Được tái sử dụng giữa API và Blade. |
| **Http/Resources** | API Resource cho payload JSON ổn định (khách sạn, phòng, booking, tour, review, support ticket, dispute). |
| **Jobs** | Tác vụ chạy nền: `ProcessVnpayIpn`, `ProcessTourIpn`, `ProcessVnpayRefund`. |
| **Listeners** | Tác vụ phụ của event: gửi email xác nhận booking, xác nhận thanh toán tour, thông báo admin về dispute, thông báo có phản hồi support ticket. |
| **Mail** | Mailable: email hóa đơn/booking và email gửi khiếu nại. |
| **Models** | Eloquent model với relationship, cast và scope. Không chứa business logic. |
| **Notifications** | Notification không gửi qua mail, ví dụ `VerifyEmailNotification`. |
| **Policies** | Phân quyền theo model (booking, khách sạn, phòng, payment, review, support ticket, dispute khách sạn/tour). |
| **Providers** | Đăng ký service container và event: binding interface của repository, broadcast auth, listener. |
| **Repositories/Contracts** | Interface repository dùng bởi service/action. |
| **Repositories/Eloquent** | Cài đặt Eloquent. Truy vấn miền, eager loading và locking thuộc lớp này; các báo cáo/aggregate dashboard có thể dùng query builder trực tiếp. |
| **Services** | Business logic cốt lõi: khả dụng, giá, booking, coupon, thanh toán, hoàn tiền, hoa hồng, payout, truy vấn/khả dụng/booking/thanh toán tour, VNPay, Gemini AI, website settings, báo cáo và tài liệu vendor. |
| **Support** | Tiện ích dùng chung như render hóa đơn và sanitize rich-text. |
| **Traits** | Hành vi model tái sử dụng: `HasUuid` (mã định danh công khai), `HasWebsiteSettings`. |
| **View/Components** | Blade view component (`AppLayout`, `GuestLayout`, `Alert`, `PageTitle`). |

## Quy ước code

- **Controller mỏng:** validate bằng Form Request, gọi Action/Service, trả về Resource, JSON response hoặc Blade view. Không đặt business logic trong controller; các controller dashboard/report được phép tổng hợp dữ liệu trực tiếp.
- **Services và Actions:** Service cung cấp năng lực nghiệp vụ có thể tái sử dụng; Action đại diện cho một use case cụ thể và dễ gọi từ HTTP, job hoặc command.
- **Repositories:** interface được bind với cài đặt Eloquent trong `AppServiceProvider`. Service phụ thuộc các interface này cho đọc/ghi dữ liệu nghiệp vụ; service báo cáo/truy vấn có thể dùng query builder trực tiếp.
- **Validation:** Form Request trong `Http/Requests/{Api,Admin,Auth}`; tái sử dụng rule giữa API và Blade khi có thể.
- **Authorization:** dùng Policy cho kiểm tra theo model, dùng middleware cho ranh giới vai trò/cổng truy cập. Dữ liệu vendor được cô lập theo `hotel_id`, dữ liệu guide theo `provider_id`.
- **Event/Listener:** listener thực hiện I/O thì cài đặt `ShouldQueue`. Xác nhận thanh toán tour theo outbox và idempotent thông qua `event_consumption_receipts`.
- **Giá trị miền:** luôn dùng enum trong `App\Enums` thay vì chuỗi thô.
- **Tiền tệ:** VND cho booking, payment, coupon và payout. Phép tính tiền nằm trong service pricing/commission.
- **Định danh:** khóa chính là số nguyên; mã công khai dạng UUID qua `HasUuid` cho user, booking, tour booking, review, tour product/provider. Các model booking/catalog chính có soft deletes.
- **Không có audit log:** dự án không có lớp audit log đa hình.
- **Truy cận dữ liệu:** MySQL ở production, SQLite in-memory cho bộ test mặc định; test integration dùng MySQL qua `phpunit.mysql.xml`.

Xem [README backend](../README.md) để biết cách cài đặt, route, queue, thanh toán và kiểm thử.
