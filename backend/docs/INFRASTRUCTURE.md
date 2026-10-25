# Hạ tầng Backend

## Queue worker

Các job chạy nền (email, thông báo, xác nhận thanh toán không đồng bộ) sử dụng queues của Laravel.

- **Khuyến nghị:** Redis. Thiết lập trong `.env`:
  - `QUEUE_CONNECTION=redis`
  - `REDIS_HOST=127.0.0.1`, `REDIS_PASSWORD=null`, `REDIS_PORT=6379`
- **Lựa chọn khác:** Database driver: `QUEUE_CONNECTION=database` và chạy `php artisan queue:table` + migrate.

**Chạy worker (development):**

```bash
php artisan queue:work
```

**Production (ví dụ Supervisor):**

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

Thay `/path/to/backend` bằng đường dẫn project thực tế.

---

## Realtime (Reverb)

Tour chat sử dụng Laravel Reverb cho tin nhắn thời gian thực. Các event implement `ShouldBroadcast`
(TourMessageSent, TourMessageInboxUpdated, TourMessageRead) đi qua queue trong
production, nên queue worker phải chạy; trong dev `QUEUE_CONNECTION=sync` chạy chúng
ngay lập tức.

**Chạy socket server (development):**

```bash
php artisan reverb:start
```

**Endpoint xác thực broadcast:**
- Admin (session cookie): `POST /broadcasting/auth` (đăng ký qua `channels` trong `bootstrap/app.php`).
- API (Bearer token, React SPA): `POST /api/v1/broadcasting/auth` (đăng ký trong `AppServiceProvider`).

**Production (ví dụ Supervisor):**

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

Laravel scheduler chạy các tác vụ định kỳ (báo cáo hàng ngày, dọn dẹp, batch availability).

**Một entry cron (chạy mỗi phút):**

```bash
* * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1
```

Thay `/path/to/backend` bằng đường dẫn project thực tế.


---

## Outbox sự kiện thanh toán tour

Xác nhận thanh toán tour sử dụng bảng outbox (`outbox_events`) và bảng biên nhận consumer
(`event_consumption_receipts`). IPN chỉ ghi sự kiện outbox trong cùng transaction với
xác nhận thanh toán/đặt phòng; việc publish xảy ra riêng qua `PublishOutboxEvents`.

### Yêu cầu Scheduler

`routes/console.php` lên lịch:

```php
Schedule::command('outbox:publish --limit=100')->everyMinute();
```

Scheduler phải chạy mỗi phút trong production. Xem phần scheduler ở trên.

### Yêu cầu Worker

Queue worker phải chạy vì `HandleTourPaymentConfirmed` được đưa vào queue. Việc publish outbox
có thể chạy qua lệnh scheduler, nhưng các side effect phía consumer cần worker.

### Retry và trạng thái failed

- `outbox.max_attempts` (mặc định 3) kiểm soát số lần thử lại.
- `outbox.backoff` (mặc định `[10, 30, 90]` giây) kiểm soát độ trễ thử lại.
- `outbox.lease_seconds` (mặc định 90) kiểm soát timeout claim lease.
- Sau số lần thử tối đa, sự kiện được đánh dấu failed (`published_at` vẫn null, attempts >= max).
- Sự kiện failed không bị xóa.

### Replay thủ công

```bash
php artisan outbox:replay {event_id}
php artisan outbox:replay --all-failed
```

Replay đặt lại attempts, xóa lease/error, đặt `available_at=now`, và ghi một audit entry.

### Giám sát

Giám sát:
- tuổi sự kiện pending lâu nhất
- số lần publish thành công/thất bại/thử lại
- số lượng dead-letter (failed)
- khôi phục lease hết hạn

Log có cấu trúc và không được bao gồm payload, PII hoặc secrets. Chỉ các định danh như
`event_id`, `event_type`, `payment_id`, `attempts`, và thông báo lỗi đã redact mới được log.

### Lưu trữ

Giữ các hàng published, failed và consumption receipt để audit/debug. Không xóa hàng outbox
trong vận hành bình thường; dùng replay thủ công cho sự kiện failed.

---

## Xử lý hoàn tiền VNPay

Các nghĩa vụ hoàn tiền nằm trong `payment_refunds`. Với `VNPAY_REFUND_ENABLED=true`, một hủy
booking khách sạn/tour hoặc tranh chấp tour tạo một hàng `pending` trong payment lock; job
queued `ProcessVnpayRefund` và lệnh scheduler `refunds:process` gửi nó tới VNPay. Với cờ
tắt, không có hàng nào được tạo và dùng đường local-accounting cũ.

### Scheduler và worker

`routes/console.php` lên lịch:

```php
Schedule::command('refunds:process')->everyMinute()->withoutOverlapping();
```

Scheduler phải chạy mỗi phút và queue worker phải chạy vì
`ProcessVnpayRefund` được đưa vào queue. `refunds:process` là lưới an toàn cho các job bị lỡ
và lease hết hạn (lease 90 giây, có thể ghi đè với `--lease`).

### Lệnh

```bash
php artisan refunds:process --limit=100 --lease=90
php artisan refunds:retry {refund_id|uuid} [--force]
```

- `refunds:process` giải phóng lease hết hạn, claim các hàng `pending` / `processing` có thể retry,
  và xử lý chúng.
- `refunds:retry` đặt lại một nghĩa vụ thành `pending` và queue lại với cùng `request_id`.
  Nó từ chối các refund đã accepted trừ khi có `--force`; chỉ dùng `--force` sau khi đã đối chiếu
  request gốc với VNPay.
- Các lệnh không bao giờ log payload, checksum, secret hay PII.

### Retry và trạng thái kết thúc

- `VNPAY_REFUND_MAX_ATTEMPTS` (mặc định 5) giới hạn số lần provider thử lại.
- Có thể retry: `99`, network timeout, HTTP 5xx. Backoff là `min(30, 2^(attempts-1))` phút.
- Không retry: `02/03/04/07/09/91/95/97` ánh xạ thành `failed`, `rejected` hoặc `needs_review`;
  `94 Request is duplicated` được coi là accepted; `94 Too many refund count` bị rejected.
- Các hàng terminal không bao giờ bị xóa; dùng `refunds:retry` để replay.

### Giám sát

Giám sát các log có cấu trúc này:

- `refund.obligation_created`: `refund_id`, `payment_type`, `payment_id`, `request_id`, amount,
  type.
- `refund.batch_summary` (mỗi lần chạy `refunds:process`): claimed, processed, pending/processing,
  failed+rejected, needs_review counts.
- `refund.outcome` (mỗi lần thử): `refund_id`, `payment_type`, `payment_id`, `request_id`,
  `status`, `response_code`, `transaction_status`, `attempts`, `next_retry_at`, lỗi đã redact.
- `refund.replay`: operator, previous status, force flag, refund/payment identifiers.

Cũng theo dõi tuổi refund pending lâu nhất, tăng trưởng `needs_review` lặp lại, khôi phục lease
hết hạn và phân phối mã phản hồi provider.

`succeeded` nghĩa là VNPay chấp nhận refund, không phải ngân hàng đã thanh toán. Đối chiếu
refund `processing`/`succeeded` trong merchant portal. Không log payload provider,
checksum, secret hay PII khách hàng.

### Checklist rollout

1. **Deploy với cờ tắt.** Chạy `php artisan migrate --force` và deploy code với
   `VNPAY_REFUND_ENABLED=false`. Migration chỉ thêm `payment_refunds`; hành vi cancel/IPN hiện
   tại không đổi.
2. **Xác minh freeze.** `php artisan config:show services.vnpay` phải báo
   `refund_enabled: false`. Hủy một booking và xác nhận thanh toán được credited local không có
   hàng `payment_refunds` và không có outbound VNPay refund call.
3. **Workers sẵn sàng.** Đảm bảo queue worker và scheduler (`refunds:process` mỗi phút) chạy
   trước khi bật cờ.
4. **Staging.** Đặt `VNPAY_REFUND_ENABLED=true` trên staging. Chạy một refund sandbox full và
   một partial, xác minh luồng status và đối chiếu merchant portal, sau đó replay cùng request
   và xác nhận không double credit.
5. **Production.** Giữ cờ tắt cho đến khi staging được sign-off. Bật cờ, theo dõi
   `refund.batch_summary`/`refund.outcome`, và giữ `refunds:retry` cho operator replay.

**Rollback:** đặt `VNPAY_REFUND_ENABLED=false`. Các job queued để nghĩa vụ `pending` mà không
gọi VNPay; không drop `payment_refunds` khi đang có nghĩa vụ mở. Fix forward hoặc settle
thủ công, sau đó redeploy.

### Fallback: settle thủ công

Tìm các nghĩa vụ cần thao tác của operator:

```bash
php artisan tinker --execute="dump(App\Models\PaymentRefund::whereIn('status', ['failed','rejected','needs_review'])->get(['id','uuid','payment_type','payment_id','amount','status','last_error'])->toArray());"
```

Hoàn tiền cho khách hàng off-platform (chuyển khoản hoặc merchant portal VNPay), sau đó ghi nhận:

```bash
php artisan refunds:settle {refund_id|uuid} --note="<bank transfer reference>" --operator=<operator>
```

Lệnh credited `refunded_amount` một lần, thêm một entry `manual` vào `payload.refunds`, lưu
note trong `provider_response.manual_settlement`, đánh dấu refund `succeeded` và ghi log
`refund.manual_settled`. Nó từ chối nghĩa vụ `pending`/`processing` (đang in-flight) và từ chối
settle lần thứ hai.

Quy tắc:
- Không bao giờ xóa hàng `payment_refunds` để "đóng" nó; giữ audit trail.
- Không bao giờ manually settle refund VNPay đã accepted (`processing` với `00/94`,
  `succeeded`); đối chiếu trong merchant portal thay vào đó.
- Đối với provider acceptance bị stuck, dùng `refunds:retry --force` chỉ sau khi đối chiếu
  request gốc.
- Ghi bank reference trong `--note`; không đặt PII khách hàng hoặc secret trong log.
