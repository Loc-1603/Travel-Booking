# Thiết lập vị trí Geoapify

Các form tạo/chỉnh sửa khách sạn sử dụng [Geoapify](https://www.geoapify.com/) để tự động hoàn thành địa chỉ và vị trí theo thời gian thực. Khi thêm hoặc cập nhật khách sạn, vendor có thể tìm kiếm địa chỉ và form sẽ tự động điền:

- **Địa chỉ** – Địa chỉ đường đầy đủ
- **Thành phố** – Tên thành phố
- **Quốc gia** – Tên quốc gia
- **Vĩ độ / Kinh độ** – Tọa độ dùng cho bản đồ và tính khoảng cách

## Thiết lập

1. **Lấy API key**
   - Truy cập [Geoapify MyProjects](https://myprojects.geoapify.com/)
   - Đăng ký hoặc đăng nhập
   - Tạo một project (hoặc dùng mặc định)
   - Sao chép API key từ bảng điều khiển project

2. **Cấu hình Laravel**
   - Thêm vào `.env`:
     ```
     GEOAPIFY_API_KEY=your_api_key_here
     ```

3. **Sử dụng**
   - Trên các form tạo/chỉnh sửa khách sạn, dùng trường "Tìm kiếm vị trí"
   - Bắt đầu gõ địa chỉ hoặc tên địa điểm
   - Chọn một gợi ý để điền địa chỉ, thành phố, quốc gia và tọa độ

## Sử dụng API

- Geoapify có gói miễn phí (ví dụ: 3.000 request/ngày)
- Các request tự động hoàn thành được proxy qua Laravel để API key giữ ở phía server
- Xem [Bảng giá Geoapify](https://www.geoapify.com/pricing/) để biết giới hạn
