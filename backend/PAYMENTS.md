# Payments (VNPay)

Currency: **VND** for all bookings, payments, coupons and payouts.

## Environment

Add to your `.env` (placeholders — fill in when sandbox/live credentials are issued;
never hard-code TmnCode / HashSecret in source):

```env
# VNPay (get credentials from https://sandbox.vnpayment.vn for test)
VNPAY_TMN_CODE=
VNPAY_HASH_SECRET=
VNPAY_URL=https://sandbox.vnpayment.vn/paymentv2/vpcpay.html
VNPAY_RETURN_URL=http://localhost:5173/vnpay-return
VNPAY_IPN_URL=http://localhost:8000/api/v1/payments/vnpay-ipn
VNPAY_VERSION=2.1.0
VNPAY_CURR_CODE=VND
VNPAY_LOCALE=vn
VNPAY_EXPIRE_MINUTES=30
FRONTEND_URL=http://localhost:5173
```

> Single IPN URL for hotel + tour. VNPay allows one IPN URL per TmnCode, so
> both payment types resolve through `GET /api/v1/payments/vnpay-ipn`
> (`UnifiedVnpayIpnController` routes by `vnp_TxnRef` prefix: `tour_...` → tour,
> otherwise hotel). The legacy `GET /api/v1/payments/tour-vnpay-ipn` is kept as
> an alias pointing at the same handler.

For local IPN testing the callback URL must be public (e.g. ngrok):
`VNPAY_IPN_URL=https://<ngrok>/api/v1/payments/vnpay-ipn`.

## Flow (hotel)

1. **Create booking** (login required) → `POST /api/v1/bookings` creates
   booking (status `pending_payment`, currency `VND`).
2. **Frontend** → `POST /api/v1/bookings/{uuid}/checkout-session`
   → `PaymentService::createCheckoutSession`
   → `VnpayService::createPaymentUrl` creates `payments` row
   (`provider=vnpay`, `status=pending`, `external_id=vnp_TxnRef`)
   and returns `checkout_url` (VNPay `pay` command, `vnp_Amount = VND x 100`,
   HMAC-SHA512 signed).
3. **Customer** pays on VNPay, is redirected to `VNPAY_RETURN_URL?uuid=...`
   (display only — never trusted for confirmation).
4. **VNPay** calls unified IPN `GET /api/v1/payments/vnpay-ipn`:
   verify checksum → find payment by `vnp_TxnRef` (hotel `payments` table,
   tour `tour_payments` table when ref starts with `tour_`) → amount check
   (`vnp_Amount == amount x 100`) → idempotency via `webhook_events`
   (`provider=vnpay`, unique `event_id`: `vnpay:...` for hotel,
   `tour:vnpay:...` for tour) → dispatch `ProcessVnpayIpn` / `ProcessTourIpn` job.
   Responds with VNPay codes: `00` success, `01` order not found,
   `02` already confirmed, `04` invalid amount, `97` invalid signature.
5. **Job** re-verifies signature; `vnp_ResponseCode=00` + `vnp_TransactionStatus=00`
   → `PaymentService::confirmPayment` (payment + booking → confirmed,
   dispatches `PaymentConfirmed` event for emails/audit). Otherwise the payment
   is marked `failed` and the booking stays payable for retry.
6. **Cancel booking** → `POST /api/v1/bookings/{uuid}/cancel` releases inventory
   and records a manual refund via `PaymentService::refund` (refunds are settled
   in the VNPay merchant portal; only local accounting is updated).

## Flow (tour)

Mirrors hotel, with tour equivalents:

1. `POST /api/v1/tour-bookings` → tour booking (`pending_payment`).
2. `POST /api/v1/tour-bookings/{uuid}/checkout-session`
   → `TourPaymentService::createCheckoutSession`
   → `TourVnpayAdapter::createPaymentUrl` creates `tour_payments` row with
   `external_id` prefixed `tour_` (keeps refs distinct from hotel payments).
   Return redirect is `VNPAY_RETURN_URL?uuid=...&type=tour`.
3. Customer pays on VNPay (same TmnCode, same IPN URL).
4. Unified IPN routes `tour_...` refs to `tour_payments` and dispatches
   `ProcessTourIpn` → `TourPaymentService::confirmPayment`
   (dispatches `TourPaymentConfirmed`). Same `00/01/02/04/97` contract.
5. Cancel → `POST /api/v1/tour-bookings/{uuid}/cancel`
   (`TourBookingService::cancelBooking`, manual refund via `TourPaymentService`).

## Refunds

- VNPay refunds are processed outside the checkout API (merchant portal / bank).
- `PaymentService::refund($payment, $amount, $reason)` validates the remaining
  refundable amount and updates `payments.refunded_amount` + status, appending an
  audit entry to `payload.refunds`.
- Commission/reporting uses `payments.refunded_amount`
  (see `CommissionService::vendorNetForBooking`).

## Queue

IPN processing runs in a queued job. Ensure a worker is running, e.g.:

```bash
php artisan queue:work
```

With `QUEUE_CONNECTION=sync`, the job runs in the same request (not recommended
for production).
