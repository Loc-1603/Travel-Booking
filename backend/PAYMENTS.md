# Thanh toán (VNPay)

Tiền tệ: **VND** cho tất cả booking, thanh toán, coupon và payout.

## Môi trường

Thêm vào `.env` (placeholder — điền khi được cấp credentials sandbox/live;
không hard-code TmnCode / HashSecret trong source):

```env
# VNPay (lấy credentials từ https://sandbox.vnpayment.vn cho test)
VNPAY_TMN_CODE=
VNPAY_HASH_SECRET=
VNPAY_URL=https://sandbox.vnpayment.vn/paymentv2/vpcpay.html
VNPAY_RETURN_URL=http://localhost:5173/vnpay-return
VNPAY_IPN_URL=http://localhost:8000/api/v1/payments/vnpay-ipn
VNPAY_VERSION=2.1.0
VNPAY_CURR_CODE=VND
VNPAY_LOCALE=vn
VNPAY_EXPIRE_MINUTES=30

# VNPay refunds (Merchant Web API)
VNPAY_API_URL=https://sandbox.vnpayment.vn/merchant_webapi/api/transaction
VNPAY_REFUND_ENABLED=false
VNPAY_REFUND_TIMEOUT=15
VNPAY_REFUND_MAX_ATTEMPTS=5
VNPAY_REFUND_CREATE_BY=system
FRONTEND_URL=http://localhost:5173
```

`VNPAY_REFUND_ENABLED` mặc định là `false`: cancel/refund giữ hành vi
local accounting hiện tại và không gọi provider. Chỉ bật sau khi
queue worker refund và credentials sandbox được xác minh.

`VNPAY_REFUND_CREATE_BY` được gửi như `vnp_CreateBy` (email/username operator);
`VNPAY_REFUND_TIMEOUT` là HTTP timeout tính bằng giây và
`VNPAY_REFUND_MAX_ATTEMPTS` giới hạn retry cho refund queue.

> Một IPN URL duy nhất cho hotel + tour. VNPay cho phép một IPN URL mỗi TmnCode, nên
cả hai loại thanh toán đều resolve qua `GET /api/v1/payments/vnpay-ipn`
(`UnifiedVnpayIpnController` route theo prefix `vnp_TxnRef`: `tour_...` → tour,
ngược lại → hotel). Legacy `GET /api/v1/payments/tour-vnpay-ipn` được giữ như
alias trỏ tới cùng handler.

Để test IPN local, callback URL phải public (ví dụ ngrok):
`VNPAY_IPN_URL=https://<ngrok>/api/v1/payments/vnpay-ipn`.

## Luồng (hotel)

1. **Tạo booking** (yêu cầu đăng nhập) → `POST /api/v1/bookings` tạo
   booking (status `pending_payment`, currency `VND`).
2. **Frontend** → `POST /api/v1/bookings/{uuid}/checkout-session`
   → `PaymentService::createCheckoutSession`
   → `VnpayService::createPaymentUrl` tạo hàng `payments`
   (`provider=vnpay`, `status=pending`, `external_id=vnp_TxnRef`)
   và trả về `checkout_url` (lệnh VNPay `pay`, `vnp_Amount = VND x 100`,
   ký HMAC-SHA512).
3. **Khách hàng** thanh toán trên VNPay, được redirect về `VNPAY_RETURN_URL?uuid=...`
   (chỉ hiển thị — không bao giờ tin cậy để xác nhận).
4. **VNPay** gọi IPN thống nhất `GET /api/v1/payments/vnpay-ipn`:
   xác minh checksum → tìm payment theo `vnp_TxnRef` (bảng `payments` của hotel,
   bảng `tour_payments` khi ref bắt đầu bằng `tour_`) → kiểm tra số tiền
   (`vnp_Amount == amount x 100`) → idempotency qua `webhook_events`
   (`provider=vnpay`, `event_id` unique: `vnpay:...` cho hotel,
   `tour:vnpay:...` cho tour) → dispatch job `ProcessVnpayIpn` / `ProcessTourIpn`.
   Trả về mã VNPay: `00` thành công, `01` không tìm thấy đơn,
   `02` đã xác nhận, `04` số tiền không hợp lệ, `97` chữ ký không hợp lệ.
5. **Job** xác minh lại chữ ký; `vnp_ResponseCode=00` + `vnp_TransactionStatus=00`
   → `PaymentService::confirmPayment` (payment + booking → confirmed,
   dispatch event `PaymentConfirmed` cho email/audit). Ngược lại payment được
   đánh dấu `failed` và booking vẫn có thể thanh toán lại.
6. **Hủy booking** → `POST /api/v1/bookings/{uuid}/cancel` giải phóng inventory
   và yêu cầu `RefundService` hoàn tiền. Với `VNPAY_REFUND_ENABLED=true` tạo
   nghĩa vụ hoàn tiền VNPay (async); với cờ tắt chỉ ghi local accounting.
   Xem [Hoàn tiền](#hoàn-tiền).

## Luồng (tour)

Tương tự hotel, với các tương đương của tour:

1. `POST /api/v1/tour-bookings` → tour booking (`pending_payment`).
2. `POST /api/v1/tour-bookings/{uuid}/checkout-session`
   → `TourPaymentService::createCheckoutSession`
   → `TourVnpayAdapter::createPaymentUrl` tạo hàng `tour_payments` với
   `external_id` có prefix `tour_` (giữ ref khác biệt với payment hotel).
   Redirect về `VNPAY_RETURN_URL?uuid=...&type=tour`.
3. Khách hàng thanh toán trên VNPay (cùng TmnCode, cùng IPN URL).
4. IPN thống nhất route ref `tour_...` tới `tour_payments` và dispatch
   `ProcessTourIpn` → `TourPaymentService::confirmPayment`
   (dispatch `TourPaymentConfirmed`). Cùng contract `00/01/02/04/97`.
5. Hủy → `POST /api/v1/tour-bookings/{uuid}/cancel`
   (`TourBookingService::cancelBooking` → `RefundService`; cùng hành vi cờ
   như refund hotel, xem [Hoàn tiền](#hoàn-tiền)).

## Hoàn tiền

Cancel và dispute refund đi qua `App\Services\RefundService`, sở hữu
nghĩa vụ hoàn tiền cho cả payment hotel (`payments`) và tour (`tour_payments`).

### Cờ tắt (mặc định)

`VNPAY_REFUND_ENABLED=false` giữ hành vi cũ: không gọi provider và payment được
credited local (`refunded_amount` + entry audit `payload.refunds`). Refund được
settle thủ công trong merchant portal VNPay.

### Cờ bật

1. Cancel/dispute gọi `RefundService::request()` trong payment lock và
   persist hàng `payment_refunds` (`pending`, type `02` full / `03` partial)
   với idempotency key:
   - `hotel-cancel:{booking_id}:{payment_id}`
   - `tour-cancel:{booking_id}:{payment_id}`
   - `tour-dispute:{dispute_id}:{payment_id}`
2. `ProcessVnpayRefund` (queue) hoặc `refunds:process` (scheduler safety net)
   claim hàng với lease 90 giây và gọi VNPay refund API.
   Retry/replay giữ cùng `vnp_RequestId`.
3. Luồng status:
   `pending → processing → succeeded | failed | rejected | needs_review`.
   `succeeded` nghĩa là **VNPay chấp nhận refund**; không phải tiền đã về
   tài khoản ngân hàng khách. Provider status `01/05/06` (hoặc request trùng
   `94`) giữ `processing` cho đến khi settle.
4. `refunded_amount` được credited chỉ khi provider chấp nhận: `00` với
   transaction status `00/05/06`, hoặc `94` với `Request is duplicated`.
   Lỗi có thể retry (`99`, timeout, HTTP 5xx) back off và retry tới
   `VNPAY_REFUND_MAX_ATTEMPTS`; hết attempts đi vào `needs_review`.
5. `94 Too many refund count (>0)` bị rejected. v1 cho phép **một nghĩa vụ
   refund mỗi payment** vì sandbox/NCB từ chối refund thứ hai; policy cần
   nhiều partial refund phải settle thủ công (`needs_review`).

`failed`, `rejected` và `needs_review` cần thao tác operator và không bao giờ
bị xóa. Xem `docs/INFRASTRUCTURE.md` cho job/command/schedule, retry
backoff, runbook replay và log giám sát.

### Settle thủ công (fallback)

Nếu VNPay từ chối hoặc không thể settle nghĩa vụ, hoàn tiền cho khách hàng
off-platform (chuyển khoản hoặc merchant portal) và đóng nghĩa vụ với audit note:

```bash
php artisan refunds:settle {refund_id|uuid} --note="<bank transfer reference>" --operator=<operator>
```

Lệnh credited `refunded_amount` một lần, thêm entry `manual` vào
`payload.refunds`, lưu note trong `provider_response.manual_settlement` và
đánh dấu refund `succeeded`. Nó từ chối nghĩa vụ `pending`/`processing`
(đang in-flight) và từ chối settle lần thứ hai. Không bao giờ xóa nghĩa vụ
để đóng nó. Thủ tục đầy đủ: `docs/INFRASTRUCTURE.md` → "Fallback: manual
settlement".

## Queue

Xử lý IPN chạy trong queued job. Đảm bảo worker đang chạy, ví dụ:

```bash
php artisan queue:work
```

Với `QUEUE_CONNECTION=sync`, job chạy trong cùng request (không khuyến nghị
cho production).
