# Backend

Laravel cung cấp REST API cho ứng dụng bán hàng.

## Mô hình MVC

```text
backend/
├── app/
│   ├── Models/             Model và quan hệ dữ liệu
│   └── Http/
│       ├── Controllers/    Controller của API
│       └── Middleware/     Xác thực và phân quyền
├── resources/views/        View của Laravel
├── routes/api.php          API routes
├── database/
│   ├── migrations/         Cấu trúc cơ sở dữ liệu
│   ├── seeders/            Dữ liệu khởi tạo
│   └── factories/          Dữ liệu kiểm thử
└── tests/                  Kiểm thử tự động
```

## Khởi tạo

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

API mặc định: `http://localhost:8000/api`.
