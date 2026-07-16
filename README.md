# Hệ thống bán hàng

Dự án được tách thành hai ứng dụng độc lập:

```text
banhangcodex/
├── frontend/   React + Vite
├── backend/    Laravel API theo mô hình MVC
├── docs/       Tài liệu và tệp thiết kế
└── archive/    Mã cũ chỉ dùng để tham khảo
```

## Chạy frontend

```bash
cd frontend
pnpm install
pnpm dev
```

Frontend mặc định chạy tại `http://localhost:5173`.

## Chạy backend

```bash
cd backend
composer install
php artisan migrate
php artisan serve
```

API mặc định chạy tại `http://localhost:8000/api`.

## Đăng nhập Google

Tạo OAuth Client loại **Web application** trong Google Cloud Console, sau đó thêm các biến sau vào `backend/.env`. Không commit file `.env` hoặc client secret.

```env
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=http://localhost:8000/api/auth/google/callback
FRONTEND_URL=http://localhost:5173
# Chỉ cần khi PHP/Windows không có CA store tin cậy:
GOOGLE_CA_BUNDLE=
```

Đăng ký chính xác Redirect URI `http://localhost:8000/api/auth/google/callback` trong Google Cloud Console. Khi deploy, thay `localhost` bằng domain HTTPS của môi trường đó ở cả `GOOGLE_REDIRECT_URI`, `FRONTEND_URL` và Google Cloud Console. Sau khi thay đổi biến môi trường, chạy:

```bash
php artisan optimize:clear
php artisan config:cache
```

Trên Windows, nếu PHP báo `cURL error 60`, tải CA bundle từ `https://curl.se/ca/cacert.pem` và đặt `GOOGLE_CA_BUNDLE` tới đường dẫn tuyệt đối của tệp đó. Trên server Linux có CA store chuẩn, không cần đặt biến này.

## Cấu hình thanh toán payOS

Backend dùng payOS cho phương thức chuyển khoản QR. Thêm các biến sau vào file `backend/.env` trên máy/server thật, không commit file `.env`:

```env
PAYOS_CLIENT_ID=điền_client_id_thật
PAYOS_API_KEY=điền_api_key_thật
PAYOS_CHECKSUM_KEY=điền_checksum_key_thật
PAYOS_BASE_URL=https://api-merchant.payos.vn
PAYOS_RETURN_URL=https://ten-mien-cua-toi.com/payment/payos/return?orderCode={payos_order_code}
PAYOS_CANCEL_URL=https://ten-mien-cua-toi.com/payment/payos/cancel?orderCode={payos_order_code}
PAYOS_CA_BUNDLE=
```

Trên Windows nếu PHP báo lỗi `cURL error 60`, tải CA bundle từ `https://curl.se/ca/cacert.pem` và đặt `PAYOS_CA_BUNDLE` tới đường dẫn file đó.

Webhook cần cấu hình trên dashboard payOS:

```text
https://ten-mien-cua-toi.com/api/payment/payos-webhook
```

Khi chạy local, public backend bằng ngrok hoặc Cloudflare Tunnel rồi cấu hình webhook dạng:

```text
https://abc.ngrok-free.app/api/payment/payos-webhook
```

## GHN sandbox và vận đơn tự động

Đơn hàng dùng hai lớp trạng thái: bước xử lý nội bộ (`pending`, `confirmed`, `preparing`, `ready_to_ship`, `handed_to_carrier`, `completed`) và trạng thái vận chuyển GHN. Admin/nhân viên chỉ thao tác xác nhận, chuẩn bị hàng, bàn giao GHN, đồng bộ hoặc gửi yêu cầu hủy. Các trạng thái giao/hoàn đến từ GHN webhook hoặc lệnh đồng bộ, không cập nhật tay.

Khai báo các biến sau trong `backend/.env`, không đưa token hay webhook secret vào Git:

```env
GHN_ENV=sandbox
# Chỉ dùng host; code tự thêm /shiip/public-api.
GHN_BASE_URL=https://dev-online-gateway.ghn.vn
GHN_TOKEN=
GHN_SHOP_ID=
GHN_WEBHOOK_SECRET=
GHN_VERIFY_SSL=true
GHN_CA_BUNDLE=

# Dùng làm fallback khi chưa có cấu hình kho trong trang quản trị.
GHN_FROM_NAME=
GHN_FROM_PHONE=
GHN_FROM_ADDRESS=
GHN_FROM_PROVINCE_ID=
GHN_FROM_DISTRICT_ID=
GHN_FROM_WARD_CODE=
```

Trang **Vận chuyển & thanh toán** trong quản trị là nơi cấu hình ưu tiên cho kho lấy hàng, cân nặng và kích thước mặc định. Cần đủ tên, số điện thoại, địa chỉ, quận/huyện và phường/xã GHN trước khi có thể bàn giao đơn. Khi chuyển production, chỉ đổi `GHN_ENV=production`, `GHN_BASE_URL=https://online-gateway.ghn.vn`, `GHN_TOKEN` và `GHN_SHOP_ID`, rồi làm mới cache cấu hình:

```bash
php artisan optimize:clear
php artisan config:cache
```

Webhook GHN là nguồn trạng thái chính:

```text
POST https://ten-mien-cua-toi.com/api/webhooks/ghn/order-status
```

Ưu tiên cấu hình header `X-GHN-Webhook-Secret` hoặc chữ ký HMAC `X-GHN-Signature` nếu dashboard GHN hỗ trợ. Nếu dashboard chỉ nhận URL, dùng URL có token dài, ngẫu nhiên và chỉ qua HTTPS:

```text
https://ten-mien-cua-toi.com/api/webhooks/ghn/order-status?token=<GHN_WEBHOOK_SECRET>
```

Khi phát triển local, public backend rồi đặt URL webhook trên dashboard GHN, không hard-code URL tunnel vào source:

```bash
ngrok http 8000
# hoặc
cloudflared tunnel --url http://localhost:8000
```

Webhook có chống xử lý trùng; scheduler là lớp đồng bộ dự phòng cho các vận đơn chưa kết thúc. Trên môi trường chạy thật, chạy cả queue worker và scheduler:

```bash
php artisan queue:work
php artisan schedule:work
```

Nếu kết nối bị ngắt đúng lúc GHN tạo đơn, hệ thống đặt yêu cầu ở trạng thái **cần xác minh** thay vì gửi lại mù quáng. Điều này tránh tạo trùng vận đơn; chỉ lỗi GHN đã xác nhận mới có nút tạo lại.

Có thể đồng bộ thủ công hoặc kiểm tra theo lô:

```bash
php artisan ghn:sync-shipments --limit=25
php artisan ghn:sync-shipments --limit=25 --now
```

Tạo vận đơn hoặc GHN callback không làm trừ/cộng kho. Hoàn tồn chỉ diễn ra khi hủy nội bộ có xác nhận hàng chưa rời kho; hàng hoàn chỉ nhập lại sau bước kho xác nhận.

## Cấu trúc MVC của backend

- Model: `backend/app/Models`
- View: `backend/resources/views`
- Controller: `backend/app/Http/Controllers`
- API routes: `backend/routes/api.php`
- Database migrations: `backend/database/migrations`

Thư mục `archive/backend_prep` là bản chuẩn bị cũ. Không chỉnh sửa hoặc chạy ứng dụng từ thư mục này.
