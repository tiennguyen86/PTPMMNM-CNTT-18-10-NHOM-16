# Lab 04 — Quản lý bản nháp bằng session và cookie

Mã mẫu và phần mở rộng đã hoàn thiện theo yêu cầu trong `../HUONG_DAN.md`.

## Mở bài

Mở terminal Ubuntu/WSL tại thư mục chứa `lab.sh`. Dùng PHP từ 8.1 và Git. Hướng dẫn chi tiết, sơ đồ, đoạn mã cần sửa, dữ liệu đối chiếu và rubric nằm trong tài liệu của bài lab.

```bash
bash lab.sh check
bash lab.sh run
```

Mở http://127.0.0.1:8104. Giữ terminal máy chủ hoạt động; dùng terminal thứ hai để kiểm thử. Nhấn Ctrl+C để dừng.

Bài này không cần PostgreSQL, Composer hoặc framework.

## Kiểm tra bài

```bash
bash lab.sh test
bash lab.sh extend
```

`test` kiểm tra phần mẫu; `extend` kiểm tra phần mở rộng đã hoàn thiện. Các bộ kiểm thử gốc được giữ nguyên.

Kết quả mở rộng cần đạt: `TESTS=7 FAILED=0`. Có luồng nhập, xem lại, sửa, hủy và hoàn tất mô phỏng; bản nháp sai hoặc hết hạn bị từ chối; các phiên sử dụng và tùy chọn giao diện tách biệt.

## Tệp nộp

Giữ mã nguồn, dữ liệu mẫu và kiểm thử. Ghi lại lệnh chạy trong README. Không đưa `.local`, mật khẩu hoặc dữ liệu cá nhân thật vào kho mã và gói nộp. Nộp đúng commit theo yêu cầu của bài; nếu dùng Actions, đính kèm lần chạy tương ứng.


## Phần trình bày giao diện

Lab này giữ CSS cục bộ tại `public/assets/css/app.css`. HTML nằm trong `public/`; các lab có nhiều trang dùng `templates/header.php` và `templates/footer.php`. Có thể lần từ các lớp `form-field`, `event-item`, `error-summary` sang CSS để đọc cách trình bày; Lab01 chỉ dùng phần trang giới thiệu.

Các lớp, nhãn và bố cục chỉ phục vụ hiển thị. Giữ nguyên khối PHP, GET/POST, tên trường, giá trị option, hidden input và thuộc tính kiểm tra đầu vào khi thực hành. Không cần JavaScript, thư viện hoặc bước build mới để chạy giao diện.


## Giao diện Material 3

Màu và kiểu chữ nằm trong `public/assets/css/tokens.css`; bố cục trong `app.css`. Không có bước build hoặc thư viện mới.

Ba lớp được tách rõ:

- `/`: sự kiện gốc của lab; tập kết quả, bộ lọc và form nghiệp vụ giữ nguyên.
- `/event.php?id=1`: chi tiết chỉ đọc, bổ sung metadata hư cấu từ `data/event-content.php`. CTA mở `/register.php` không kèm tham số mới.
- `/ui-demo.php`: sáu sự kiện hư cấu, ID `demo-...` từ `data/ui-demo-events.php`. `/ticket-preview.php?id=demo-open-source` chỉ thử vé/ghế trong bộ nhớ trang, không có form hoặc API ghi dữ liệu.

Khu thử có 96 ghế cố định, 68 khả dụng (16 VIP và 52 Tiêu chuẩn). Hai vé VIP mẫu 350.000 đồng cho tổng 700.000 đồng. Đổi hạng, đổi sự kiện hoặc tải lại sẽ bỏ lựa chọn không phù hợp; không có trạng thái đã giữ chỗ.

Ảnh SVG tự tạo, lưu cục bộ và ghi quyền CC0 trong `public/assets/images/LICENSE.txt`. `presentation.js` chỉ xử lý ảnh lỗi; `ticket-preview.js` chỉ chạy trên trang thử. Khi tắt JavaScript, vẫn đọc được nội dung và dùng form lab như cũ; khu chọn thử được khóa kèm giải thích.

Không đưa ID demo, vé hoặc ghế vào form, Session, Cookie, CSDL hoặc test cũ. Phần mở rộng nghiệp vụ được thực hiện độc lập với khu thử giao diện. Không có backend bán vé, thanh toán hoặc gửi email.

## Phần mở rộng đã hoàn thiện

- `src/DraftGuard.php`: chỉ chấp nhận nháp có cấu trúc hợp lệ, đúng ID sự kiện, timestamp nguyên không ở tương lai, TTL dương và tuổi nhỏ hơn TTL. Đúng ngưỡng hết hạn bị từ chối.
- `public/confirm.php`: kiểm tra nháp trước khi hiển thị, gửi `event_id` ẩn cùng token CSRF khi xác nhận.
- `public/complete.php`: giữ kiểm tra CSRF, từ chối ID thiếu/sai/không phải số nguyên; chỉ ghi `SIMULATED_OK` sau khi guard chấp nhận. Không ghi CSDL.

Kiểm thử bổ sung dữ liệu sai và biên:

```bash
php tests/guard-regression.php
```

Kết quả: mẫu `TESTS=6 FAILED=0`, mở rộng `TESTS=7 FAILED=0`, hồi quy `TESTS=14 FAILED=0`.
