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

## Cấu trúc MVC của backend

- Model: `backend/app/Models`
- View: `backend/resources/views`
- Controller: `backend/app/Http/Controllers`
- API routes: `backend/routes/api.php`
- Database migrations: `backend/database/migrations`

Thư mục `archive/backend_prep` là bản chuẩn bị cũ. Không chỉnh sửa hoặc chạy ứng dụng từ thư mục này.
