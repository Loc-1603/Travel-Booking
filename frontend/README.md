# Travel-Booking — Ứng dụng khách hàng (React)

Ứng dụng SPA dành cho khách hàng: tìm kiếm khách sạn và tour, đặt booking, thanh toán VNPay, yêu thích, đánh giá, support ticket và chat tour realtime.

## Công nghệ

- React 19 + React DOM 19
- Vite 7 + `@vitejs/plugin-react`
- Tailwind CSS 4 (`@tailwindcss/vite`)
- TanStack React Query 5 + Axios
- React Router 7
- i18next + react-i18next (tiếng Anh / tiếng Việt)
- Leaflet + React-Leaflet
- Laravel Echo + Pusher JS trên nền Laravel Reverb
- Vitest 5 + Testing Library + axe-core

## Yêu cầu

- Node.js `^20.19.0` hoặc `>=22.12.0`
- Backend Laravel đang chạy tại `http://127.0.0.1:8000`

## Cài đặt

```bash
npm install
npm run dev
```

Mở `http://localhost:5173`. Vite proxy `/api` về `http://127.0.0.1:8000`, nên API base mặc định là `/api/v1` và không cần file env khi phát triển local.

File `.env.local` (không bắt buộc):

```env
VITE_API_URL=http://localhost:8000/api/v1
VITE_REVERB_APP_KEY=
VITE_REVERB_HOST=localhost
VITE_REVERB_PORT=8080
VITE_REVERB_SCHEME=http
```

Nếu không có `VITE_REVERB_APP_KEY`, tính năng chat sẽ tự động chuyển sang polling qua REST.

## Các lệnh npm

```bash
npm run dev        # Vite dev server, cổng 5173
npm run build      # build production ra dist/
npm run preview    # xem bản build production
npm run lint       # ESLint flat config
npm run test       # chạy Vitest (jsdom)
npm run test:watch # chạy Vitest ở chế độ watch
```

## Danh sách route

| Route | Trang | Mục đích |
|-------|------|----------|
| `/` | `Home` | Tìm kiếm theo điểm đến, ngày và số khách |
| `/hotels` | `HotelList` | Danh sách kết quả, bộ lọc đồng bộ URL, sắp xếp, phân trang |
| `/hotels/map` | `MapSearch` | Tìm kiếm khách sạn trên bản đồ |
| `/hotels/:id` | `HotelDetail` | Thông tin khách sạn, phòng, khả dụng, đánh giá, phân tích cảm xúc review bằng AI |
| `/book` | `Booking` | Chọn số lượng phòng và tạo booking khách sạn |
| `/checkout/:uuid` | `Checkout` | Tóm tắt booking và thanh toán VNPay |
| `/vnpay-return` | `VnpayReturn` | Trang kết quả khi khách quay lại từ VNPay |
| `/bookings` | `MyBookings` | Booking khách sạn, hóa đơn, hủy/gửi khiếu nại |
| `/tours` | `Tours` | Danh mục tour và bộ lọc |
| `/tours/province/:slug` | `TourProvinceDetail` | Tour và guide theo tỉnh/thành |
| `/guides/:uuid` | `GuideDetail` | Hồ sơ guide, tour, lịch khả dụng |
| `/tours/:uuid` | `TourDetail` | Chi tiết tour, thư viện ảnh, lịch slot, đánh giá |
| `/tours/book/:uuid` | `TourBookingWizard` | Chọn slot, ngày, số khách và xem trước giá |
| `/tour-checkout/:uuid` | `TourCheckout` | Tóm tắt tour và thanh toán VNPay |
| `/tour-bookings` | `MyTourBookings` | Booking tour, hóa đơn, chat, nút đánh giá |
| `/tour-bookings/:uuid/review` | `TourBookingReview` | Gửi đánh giá tour khi booking đã xác nhận, đang diễn ra hoặc hoàn thành |
| `/favorites` | `Favorites` | Khách sạn, tour và guide đã lưu |
| `/support` | `Support` | Danh sách support ticket của khách |
| `/support/new` | `SupportTicketNew` | Tạo support ticket mới |
| `/support/:id` | `SupportTicketDetail` | Nội dung ticket và các phản hồi |
| `/profile` | `Profile` | Hồ sơ khách hàng, ảnh đại diện và mật khẩu |
| `/login` | `Login` | Đăng nhập khách hàng (token Sanctum) |
| `/register` | `Register` | Đăng ký khách hàng + xác thực email |
| `/verify-email` | `VerifyEmail` | Trạng thái xác thực email và gửi lại mã |

Các đường dẫn cũ vẫn dùng được: `/wishlist` và `/saved-tours` chuyển hướng về `/favorites`.

## Cấu trúc thư mục

```text
src/
├── components/        # UI dùng chung (card khách sạn/tour, gallery, chat box, modal, layout, ui/)
├── contexts/          # Auth, ngôn ngữ, toast, website settings
├── hooks/             # Wishlist, saved guides, tour chat, countdown, lazy image
├── i18n/              # Cấu hình i18next + locales/en.json, locales/vi.json
├── lib/               # API client, Echo singleton, helper ngày/tìm kiếm/giá
├── pages/             # Trang theo route
├── styles/            # Style dùng chung
├── test/              # Helper test (axe)
├── App.jsx            # Định nghĩa route và provider
├── main.jsx           # Điểm vào
└── index.css          # Entry của Tailwind
```

Test unit/component nằm ngay cạnh source dưới dạng `*.test.jsx` / `*.test.tsx`. Ứng dụng **không** có thư mục `src/services/`; mọi request HTTP đi qua `src/lib/api.js`.

## Dữ liệu và xác thực

- `src/lib/api.js` tạo Axios client với base URL là `VITE_API_URL` hoặc `/api/v1`, tự gắn header `Authorization: Bearer <token>` đọc từ `localStorage`, và xóa phiên đăng nhập khi gặp HTTP 401.
- Response API được thiết kế theo dạng `{ success, data, message? }`.
- Trạng thái đăng nhập nằm trong `AuthContext`; token được lưu trong `localStorage`.
- Khách hàng đăng nhập qua `/api/v1/login` (personal access token của Sanctum). Tài khoản admin, vendor và guide dùng cổng Blade của backend, không dùng SPA này.
- Website settings (tên site, logo, favicon, locale, liên hệ, SEO) được tải qua `WebsiteSettingsContext`, dùng để branding và chọn ngôn ngữ mặc định.
- React Query dùng stale time 60 giây; các trang được lazy-load, riêng `Home` được import trực tiếp.
- Review trả về cho SPA đã loại các review đang bị ẩn; việc kiểm duyệt diễn ra sau khi đăng (post-publication), không có hàng đợi duyệt trước.

## Chat tour realtime

- `src/lib/echo.js` tạo Laravel Echo singleton qua Reverb (Pusher protocol), xác thực tại `/api/v1/broadcasting/auth` bằng Bearer token.
- `useTourChat` tải danh sách tin nhắn từ `/tour-bookings/{uuid}/messages` và đăng ký kênh của booking.
- Nếu Reverb không khả dụng, chat vẫn hoạt động nhờ polling (5 giây khi socket down, 30 giây như heartbeat khi socket live).
- Trạng thái đã đọc chỉ được cập nhật khi người dùng có tương tác trên trang; mở thread không tự đánh dấu đã đọc.
- Chat chỉ mở sau khi thanh toán tour được xác nhận.

## Đa ngữ (i18n)

- Bộ catalog tiếng Anh và tiếng Việt nằm ở `src/i18n/locales/`.
- Thứ tự nhận diện ngôn ngữ: `localStorage` rồi tới trình duyệt; lựa chọn của người dùng được lưu ở `i18nextLng` / `i18nextLngChosen`.
- Khi khách chưa chủ động chọn ngôn ngữ, ngôn ngữ mặc định có thể lấy từ website settings của backend.
- Helper định dạng ngày nằm ở `src/lib/dates.js`; helper định dạng giá/rating/ngày nằm ở `src/lib/utils.js`.

## Kiểm thử

```bash
npm run test
npm run test:watch
npm run lint
```

Vitest dùng `jsdom`, nạp `vitest.setup.ts` (`@testing-library/jest-dom`) và bao gồm `src/**/*.{test,spec}.{js,jsx,ts,tsx}`. Kiểm tra khả năng tiếp cận dùng helper axe-core trong `src/test/`.

## Build và triển khai

```bash
npm run build
```

Triển khai thư mục static `dist/` (Vercel/Netlify/S3+CloudFront/Nginx). Đặt `VITE_API_URL` trỏ tới URL API production lúc build và cấu hình `VITE_REVERB_*` cho Reverb server production. API cần cho phép origin của frontend qua CORS và hỗ trợ Sanctum/broadcast auth.

Xem [README gốc](../README.md) và [README backend](../backend/README.md) để có hướng dẫn cài đặt và vận hành đầy đủ của hệ thống.
