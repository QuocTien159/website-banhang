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

## Cấu hình thanh toán payOS

Backend dùng payOS cho phương thức chuyển khoản QR. Thêm các biến sau vào file `backend/.env` trên máy/server thật, không commit file `.env`:

```env
PAYOS_CLIENT_ID=điền_client_id_thật
PAYOS_API_KEY=điền_api_key_thật
PAYOS_CHECKSUM_KEY=điền_checksum_key_thật
PAYOS_BASE_URL=https://api-merchant.payos.vn
PAYOS_RETURN_URL=https://ten-mien-cua-toi.com/account/orders/{order_id}/qr-payment?payosResult=return
PAYOS_CANCEL_URL=https://ten-mien-cua-toi.com/account/orders/{order_id}/qr-payment?payosResult=cancel
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
