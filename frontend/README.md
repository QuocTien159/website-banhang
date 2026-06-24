# Frontend

Ứng dụng giao diện React, được đóng gói bằng Vite.

## Cấu trúc chính

```text
frontend/
├── src/
│   ├── app/
│   │   ├── components/  Trang, layout và UI dùng chung
│   │   ├── data/        Dữ liệu tĩnh
│   │   ├── services/    Kết nối Laravel API
│   │   └── store/       Trạng thái ứng dụng
│   ├── styles/          CSS toàn cục
│   └── main.tsx         Điểm khởi chạy
├── index.html
└── vite.config.ts
```

## Lệnh phát triển

```bash
copy .env.example .env
pnpm install
pnpm dev
pnpm build
```

Có thể thay đổi địa chỉ Laravel API bằng biến `VITE_API_BASE_URL` trong `.env`.
