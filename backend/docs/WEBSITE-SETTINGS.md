# Tính năng Cài đặt Website

Tài liệu này giải thích cách sử dụng và mở rộng tính năng cài đặt website trong Nền tảng Đặt phòng Khách sạn.

## Tổng quan

Tính năng cài đặt website cho phép super administrator tùy chỉnh nhiều khía cạnh của website bao gồm:

- **Cài đặt chung**: Tên site, mô tả, thông tin liên hệ
- **Tài sản thương hiệu**: Upload/quản lý logo và favicon
- **Mạng xã hội**: Link đến các hồ sơ mạng xã hội
- **Cài đặt SEO**: Meta tags, keywords, Google Analytics
- **Chế độ bảo trì**: Bật/tắt chế độ bảo trì với thông điệp tùy chỉnh

## Truy cập

Cài đặt website chỉ có thể truy cập bởi người dùng có vai trò **SUPER_ADMIN**:
1. Đăng nhập vào admin panel
2. Điều hướng đến "Website Settings" trong menu sidebar
3. URL: `/admin/website-settings`

## Các cài đặt có sẵn

### Cài đặt chung
- **Tên site**: Tên chính của website (bắt buộc)
- **Mô tả site**: Mô tả ngắn về website
- **Email liên hệ**: Địa chỉ email liên hệ chính
- **Số điện thoại liên hệ**: Số điện thoại liên hệ
- **Địa chỉ**: Địa chỉ vật lý

### Tài sản thương hiệu
- **Logo site**: Upload ảnh logo (JPEG, PNG, JPG, GIF, SVG - tối đa 2MB)
- **Favicon**: Upload favicon (ICO, PNG, JPG, JPEG - tối đa 1MB)

### Link mạng xã hội
- **URL Facebook**: Link đến trang Facebook
- **URL Twitter**: Link đến hồ sơ Twitter
- **URL Instagram**: Link đến hồ sơ Instagram
- **URL LinkedIn**: Link đến trang LinkedIn

### Cài đặt SEO
- **Meta Title**: Title mặc định cho SEO
- **Meta Description**: Description mặc định cho SEO
- **Meta Keywords**: Keywords cho SEO (phân tách bằng dấu phẩy)
- **Google Analytics**: Mã theo dõi Google Analytics

### Chế độ bảo trì
- **Bật chế độ bảo trì**: Toggle bật/tắt
- **Thông điệp bảo trì**: Thông điệp tùy chỉnh hiển thị trong thời gian bảo trì

## Sử dụng trong code

### Sử dụng Service Class

```php
use App\Services\WebsiteSettingsService;

// Lấy từng cài đặt
$siteName = WebsiteSettingsService::getSiteName();
$siteLogo = WebsiteSettingsService::getSiteLogo();
$isMaintenance = WebsiteSettingsService::isMaintenanceMode();

// Lấy bất kỳ cài đặt nào theo key
$email = WebsiteSettingsService::get('site_email', 'default@example.com');

// Lấy tất cả cài đặt dạng array
$allSettings = WebsiteSettingsService::getAllSettings();
```

### Sử dụng Trait

```php
use App\Traits\HasWebsiteSettings;

class YourClass
{
    use HasWebsiteSettings;
    
    public function someMethod()
    {
        $siteName = $this->getSiteName();
        $isMaintenance = $this->isMaintenanceMode();
    }
}
```

### Sử dụng Blade Components

```blade
<!-- Include meta tags trong layout head -->
<x-website.meta />

<!-- Lấy từng cài đặt trong views -->
{{ WebsiteSettingsService::getSiteName() }}

<!-- Hiển thị logo nếu tồn tại -->
@if(WebsiteSettingsService::getSiteLogo())
    <img src="{{ WebsiteSettingsService::getSiteLogo() }}" alt="Logo">
@endif
```

## Lưu trữ file

Các file upload (logo, favicon) được lưu tại:
- **Đường dẫn storage**: `storage/app/public/website/`
- **URL public**: `public/storage/website/`

Đảm bảo chạy: `php artisan storage:link` để file có thể truy cập công khai.

## Chế độ bảo trì

Khi chế độ bảo trì được bật:
- Tất cả route không phải admin sẽ hiển thị trang bảo trì
- Admin panel vẫn truy cập được
- Các request API trả về JSON với trạng thái bảo trì
- Các request AJAX nhận response JSON phù hợp

### Tùy chỉnh trang bảo trì

View bảo trì nằm tại: `resources/views/maintenance.blade.php`

Bạn có thể tùy chỉnh styling và nội dung theo nhu cầu.

## Database Schema

Cài đặt được lưu trong bảng `platform_settings`:
- `key` (string, primary): Định danh cài đặt
- `value` (text, nullable): Giá trị cài đặt
- `timestamps`: Thời gian tạo/cập nhật

## Thêm cài đặt mới

### 1. Thêm vào Controller

Trong `WebsiteSettingsController@index`, thêm cài đặt mới:

```php
$new_setting => PlatformSetting::get('new_setting', 'default_value'),
```

### 2. Thêm validation

Trong `WebsiteSettingsRequest`, thêm rule validation:

```php
'new_setting' => 'required|string|max:255',
```

### 3. Thêm vào View

Trong `index.blade.php`, thêm form field:

```blade
<div class="mb-3">
    <label for="new_setting" class="form-label">New Setting</label>
    <input type="text" class="form-control" id="new_setting" name="new_setting" value="{{ $settings['new_setting'] }}">
</div>
```

### 4. Thêm Service Method (Tùy chọn)

Trong `WebsiteSettingsService`, thêm helper method:

```php
public static function getNewSetting(): string
{
    return self::get('new_setting', 'default_value');
}
```

## Cân nhắc bảo mật

- Chỉ người dùng SUPER_ADMIN mới truy cập được cài đặt website
- File upload được validate về loại và kích thước
- File cũ tự động bị xóa khi upload file mới
- Tất cả input được validate và sanitize đúng cách

## Hiệu năng

Cài đặt được cache bởi query cache của Laravel khi truy cập qua model `PlatformSetting`. Với ứng dụng traffic cao, cân nhắc implement thêm các lớp cache trong service class.

## Xử lý sự cố

### File không hiển thị
- Chạy `php artisan storage:link`
- Kiểm tra quyền file trong `storage/app/public/website/`
- Xác minh `APP_URL` trong file `.env`

### Chế độ bảo trì không hoạt động
- Đảm bảo middleware `CheckMaintenanceMode` được đăng ký
- Kiểm tra middleware được áp dụng cho routes của bạn
- Xác minh cài đặt được lưu đúng trong database

### Cài đặt không lưu
- Kiểm tra bạn có vai trò SUPER_ADMIN
- Xác minh validation form pass
- Kiểm tra kết nối database và quyền
