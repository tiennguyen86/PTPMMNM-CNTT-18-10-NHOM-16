# Lab 01 — Vận hành công cụ quét & kiểm tra cấu trúc API trên Linux

**Đề tài số 2: Xây dựng công cụ kiểm tra thay đổi và tính tương thích của cấu trúc dữ liệu API**
**Nhóm 16 — Lớp CNTT-18-10** (Nguyễn Hữu Tiến - 1871020569, Nguyễn Việt Anh - 1871020047, Nguyễn Tiến Thuận - 1871020562)

*Trạng thái: Hoàn thành bài mẫu & bài mở rộng (CHECK_ENV=PASS, SHELL_CASES=4 FAILED=0)*

## Mở bài

Mở terminal Ubuntu/WSL tại thư mục chứa `lab.sh`. Dùng PHP từ 8.1 và Git. Hướng dẫn chi tiết, sơ đồ, đoạn mã cần sửa, dữ liệu đối chiếu và rubric nằm trong tài liệu của bài lab.

```bash
bash lab.sh check
bash lab.sh run
```

Mở http://127.0.0.1:8101. Giữ terminal máy chủ hoạt động; dùng terminal thứ hai để kiểm thử. Nhấn Ctrl+C để dừng.

Bài này không cần PostgreSQL, Composer hoặc framework.

## Kiểm tra bài

```bash
bash lab.sh test
bash lab.sh extend
```

`test` kiểm tra phần mẫu; `extend` kiểm tra phần tự làm. Các vị trí TODO là nhiệm vụ cần hoàn thiện; không sửa test để bỏ qua lỗi.

Kết quả mở rộng cần đạt: `SHELL_CASES=4 FAILED=0`. Hoàn thành script kiểm tra môi trường và script quét mã PHP; xử lý được thư mục thiếu, thư mục rỗng, tệp sai cú pháp và đường dẫn có khoảng trắng.

## Tệp nộp

Giữ mã nguồn, dữ liệu mẫu và kiểm thử. Ghi lại lệnh chạy trong README. Không đưa `.local`, mật khẩu hoặc dữ liệu cá nhân thật vào kho mã và gói nộp. Nộp đúng commit theo yêu cầu của bài; nếu dùng Actions, đính kèm lần chạy tương ứng.


## Phần trình bày giao diện

Lab này giữ CSS cục bộ tại `public/assets/css/app.css`. HTML nằm trong `public/`; các lab có nhiều trang dùng `templates/header.php` và `templates/footer.php`. Có thể lần từ các lớp `form-field`, `event-item`, `error-summary` sang CSS để đọc cách trình bày; Lab01 chỉ dùng phần trang giới thiệu.

Các lớp, nhãn và bố cục chỉ phục vụ hiển thị. Giữ nguyên khối PHP, GET/POST, tên trường, giá trị option, hidden input và thuộc tính kiểm tra đầu vào khi thực hành. Không cần JavaScript, thư viện hoặc bước build mới để chạy giao diện.


## Giao diện Material 3

Màu và kiểu chữ nằm trong `public/assets/css/tokens.css`; bố cục trong `app.css`. Không có bước build hoặc thư viện mới.
