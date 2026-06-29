# TienProSport - Ghi chú cấu trúc cho người học

Tài liệu này mô tả ngắn cách project đang được tổ chức và các luồng nghiệp vụ chính. Nội dung chỉ để đọc hiểu, không thay đổi hành vi hệ thống.

## Frontend

Thư mục chính: `frontend/src/app`

- `components/user`: các trang và component phía khách hàng như trang chủ, sản phẩm, giỏ hàng, thanh toán, tài khoản.
- `components/admin`: các trang quản trị/vận hành như sản phẩm, đơn hàng, kho, đánh giá, nhân viên.
- `components/layout`: layout dùng chung như header người dùng và sidebar admin.
- `services`: nơi gọi API backend.
- `store`: state dùng chung cho auth và giỏ hàng.
- `constants`: hằng số dùng chung như trạng thái đơn hàng, thanh toán, role.
- `utils`: helper dùng chung như format tiền và format ngày.

Nguyên tắc đọc code frontend:

1. Trang gọi service để lấy dữ liệu.
2. Service gọi API qua `apiClient`.
3. UI chỉ render dữ liệu đã chuẩn hóa, tránh hard-code dữ liệu nghiệp vụ nếu backend đã có.

## Backend

Thư mục chính: `backend/app`

- `Http/Controllers/Api`: controller API public và khách hàng.
- `Http/Controllers/Api/Admin`: controller API cho admin/staff.
- `Models`: model Eloquent tương ứng database.
- `Services`: logic nghiệp vụ dùng lại nhiều nơi, ví dụ kho, khuyến mãi, vận chuyển/thanh toán.
- `Support`: hằng số nghiệp vụ như role, trạng thái đơn hàng, trạng thái thanh toán.
- `Http/Middleware`: middleware phân quyền.

Nguyên tắc đọc code backend:

1. Route trong `backend/routes/api.php` quyết định API cần auth/role nào.
2. Controller validate request và điều phối service/model.
3. Service xử lý nghiệp vụ có rủi ro cao hoặc dùng lại nhiều nơi.
4. Model mô tả dữ liệu và quan hệ.

## Luồng đặt hàng

1. Khách chọn biến thể sản phẩm và thêm vào giỏ.
2. Checkout gửi thông tin người nhận, địa chỉ hành chính và phương thức thanh toán.
3. Backend tự kiểm tra tồn kho, tự tính phí ship và tự tính tổng tiền.
4. Khi đơn được tạo, backend trừ tồn kho qua `InventoryService`.
5. Nếu có mã khuyến mãi, backend lưu lịch sử sử dụng trong transaction.

Backend phải tự tính tiền/ship vì dữ liệu từ frontend có thể bị sửa trước khi gửi.

## Luồng thanh toán QR

1. Khách chọn thanh toán chuyển khoản QR.
2. Backend tạo nội dung chuyển khoản và URL VietQR.
3. Khách bấm “Tôi đã chuyển khoản”.
4. Đơn chuyển sang trạng thái chờ admin xác nhận.
5. Admin/staff theo quyền được cập nhật trạng thái thanh toán theo API hiện tại.

## Luồng quản lý kho

Không sửa tồn kho trực tiếp trong form sản phẩm.

- Nhập kho tạo phiếu nhập và cộng tồn kho.
- Bán hàng trừ tồn kho.
- Hủy đơn hoàn tồn kho.
- Điều chỉnh kho phải có lý do.
- Mọi thay đổi tồn kho đi qua `InventoryService` để ghi lịch sử biến động kho.

## Luồng phân quyền

Hệ thống có 3 role:

- `customer`: khách mua hàng.
- `staff`: nhân viên vận hành, được xử lý sản phẩm, đơn hàng, kho, trả hàng, đánh giá.
- `admin`: toàn quyền, thêm quyền quản lý nhân viên, khách hàng, báo cáo, khuyến mãi, thông báo, cấu hình hệ thống.

Frontend chỉ ẩn menu theo role để dễ dùng. Backend mới là lớp chặn chính bằng middleware role.

## Luồng doanh thu

Doanh thu chỉ tính từ đơn đã hoàn thành/giao thành công. Đơn chờ xác nhận, đang xử lý, đang giao hoặc đã hủy không được tính vào doanh thu vì tiền chưa chắc chắn thuộc về shop.
