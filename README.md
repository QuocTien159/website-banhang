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

## Cấu trúc MVC của backend

- Model: `backend/app/Models`
- View: `backend/resources/views`
- Controller: `backend/app/Http/Controllers`
- API routes: `backend/routes/api.php`
- Database migrations: `backend/database/migrations`

Thư mục `archive/backend_prep` là bản chuẩn bị cũ. Không chỉnh sửa hoặc chạy ứng dụng từ thư mục này.
