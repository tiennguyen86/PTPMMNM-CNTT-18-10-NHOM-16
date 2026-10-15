# ⚡ APISchemaDiff — Công cụ kiểm tra thay đổi và tính tương thích của cấu trúc dữ liệu API

> **Đề tài số 2** — Học phần: **Phát triển phần mềm nguồn mở (PTPMMNM)**  
> **Khoa Công nghệ Thông tin — Trường Đại học Đại Nam (FIT-DNU)**

---

## 👥 Thông tin nhóm thực hiện

- **Lớp**: CNTT-18-10
- **Nhóm**: 16

| STT | Họ và tên | Mã sinh viên | Vai trò trong nhóm |
|:---:|:---|:---:|:---|
| 1 | **Nguyễn Hữu Tiến** | **1871020569** | **Nhóm trưởng** (Quản trị repo, kiến trúc hệ thống, Lab 1 & 2) |
| 2 | **Nguyễn Việt Anh** | **1871020047** | **Thành viên** (Xây dựng module OOP, thuật toán Diff, Lab 2 & 3) |
| 3 | **Nguyễn Tiến Thuận** | **1871020562** | **Thành viên** (Giao diện Web, kiểm thử tự động, tài liệu) |

---

## 🎯 Giới thiệu Đề tài

Trong kiến trúc hướng dịch vụ (Microservices) và phát triển Web API, việc bảo trì và nâng cấp các phiên bản API là thách thức lớn. Khi một trường trong cấu trúc JSON Schema / OpenAPI bị xóa, đổi kiểu dữ liệu hoặc thêm một trường bắt buộc mới, các ứng dụng Mobile App hoặc Frontend client phiên bản cũ sẽ gặp lỗi nghiêm trọng (**Breaking Changes**).

**APISchemaDiff** là công cụ mã nguồn mở được nhóm phát triển nhằm:
1. **Tự động hóa phát hiện thay đổi (Schema Diff)**: So sánh chi tiết từng trường dữ liệu giữa 2 phiên bản API Schema (V1.0 vs V2.0).
2. **Đánh giá tính tương thích ngược (Backward Compatibility Evaluation)**: Phân loại chính xác các thay đổi thành:
   - 🔴 **Breaking Changes (Phá vỡ tương thích)**: Xóa trường, đổi kiểu dữ liệu (`integer` sang `string`, ...), thêm trường mới ở dạng bắt buộc (`required`), biến trường tùy chọn thành bắt buộc.
   - 🟢 **Non-breaking Changes (Tương thích ngược an toàn)**: Thêm trường mới ở dạng tùy chọn (`optional`), nới lỏng trường bắt buộc thành tùy chọn.
3. **Cung cấp đa giao diện**: Hỗ trợ dòng lệnh Shell Script tự động hóa trong CI/CD pipeline (Lab 1) và giao diện Web tương tác trực quan (Lab 3).

---

## 📂 Cấu trúc dự án theo bài Lab

Dự án được phân rã thành các module bài thực hành hoàn chỉnh:

| Bài Lab | Tên module & Nội dung theo Đề tài 2 | Trạng thái | Bộ kiểm thử tự động |
|:---|:---|:---:|:---:|
| **[Lab 01](lab1/)** | **Vận hành Linux & Shell Script tự động quét, xác thực cú pháp mã nguồn và API Schema** | **Hoàn thành** | `CHECK_ENV=PASS`<br>`SHELL_CASES=4 FAILED=0` |
| **[Lab 02](lab2/)** | **Lập trình OOP PHP: Xây dựng Module kiểm tra tính tương thích API (`ApiCompatibilityChecker`)** | **Hoàn thành** | `TESTS=5 FAILED=0`<br>`TESTS=8 FAILED=0` |
| **[Lab 03](lab3/)** | **Ứng dụng Web: So sánh Visual Diff API Schema, bộ lọc đa điều kiện và form kiểm tra tức thì** | **Hoàn thành** | `TESTS=7 FAILED=0`<br>`TESTS=8 FAILED=0` |
| **[Lab 04](lab4/)** | Quản lý phiên làm việc (Session/Cookie) & Phân quyền kiểm thử API | Khởi tạo | Đang phát triển |
| **[Lab 05](lab5/)** | Tích hợp CSDL PostgreSQL lưu trữ lịch sử kiểm tra và phiên bản Schema | Khởi tạo | Đang phát triển |

---

## ⚙️ Chi tiết kỹ thuật từng bài Lab

### 1. Lab 01 — Tự động hóa Shell Script trên Linux (`lab1/Ma_nguon/scripts/scan-source.sh`)
- Script Shell chạy trong môi trường Ubuntu/WSL tự động quét đệ quy các tệp mã nguồn và cấu trúc API.
- Bắt lỗi thư mục không tồn tại hoặc thiếu môi trường PHP (`mã thoát 2`).
- Duyệt đệ quy và kiểm tra cú pháp từng tệp mà không bị dừng đột ngột khi gặp tệp lỗi.
- Đếm tổng số tệp (`TOTAL`), số tệp hợp lệ (`PASS`), số tệp lỗi (`FAIL`).
- Trả về mã thoát chuẩn POSIX: `0` (tất cả hợp lệ), `1` (có tệp lỗi), `2` (đầu vào không hợp lệ / thư mục rỗng).

### 2. Lab 02 — Lõi hướng đối tượng phân tích tương thích (`lab2/Ma_nguon/src/`)
- Lớp **`ApiCompatibilityChecker`**: So sánh 2 mảng định nghĩa cấu trúc API Schema, phát hiện các trường hợp vi phạm tính tương thích ngược:
  - `REMOVED`: Trường bị xóa khỏi bản mới -> Cảnh báo Breaking.
  - `TYPE_CHANGED`: Kiểu dữ liệu bị thay đổi -> Cảnh báo Breaking.
  - `ADDED_REQUIRED`: Thêm trường mới nhưng bắt buộc client phải gửi -> Cảnh báo Breaking.
  - `ADDED_OPTIONAL`: Thêm trường tùy chọn an toàn -> Tương thích ngược.
- Lớp **`RegistrationPolicy`** & **`CapacityPolicy`**: Kiểm soát ngưỡng giới hạn request (Rate Limiting/Capacity) theo quy tắc 3 trạng thái `confirmed`, `waiting`, `rejected`.

### 3. Lab 03 — Giao diện Web so sánh Diff & Kiểm tra tương thích (`lab3/Ma_nguon/public/api-diff.php`)
- **Giao diện Web hiện đại**: Xây dựng theo ngôn ngữ thiết kế Material 3, hỗ trợ tương tác trực quan.
- **Kịch bản mẫu sẵn có**:
  - *User Profile API (V1.0 ➔ V2.0)*: Minh họa các ca Breaking Changes điển hình (xóa trường `email`, đổi type `id`, thêm required `role`).
  - *Order Checkout API (V1.0 ➔ V1.1)*: Minh họa bản cập nhật an toàn tương thích ngược 100%.
  - *Product Catalog API (V1.0 ➔ V2.0)*: Minh họa thay đổi phá vỡ cấu trúc catalog.
- **Trình nhập tùy biến**: Cho phép dán trực tiếp bất kỳ cấu trúc JSON Schema nào để phân tích ngay lập tức.
- **Bộ lọc kết quả**: Tích hợp tìm kiếm theo tên trường và phân loại mức độ nghiêm trọng.

---

## 🚀 Hướng dẫn cài đặt & Chạy ứng dụng

### 1. Yêu cầu hệ thống
- Hệ điều hành: **Ubuntu / WSL2 (Windows)**
- PHP: Phiên bản **8.1 trở lên** (`php-cli`)
- Git

### 2. Các lệnh thực thi

Di chuyển vào thư mục `Ma_nguon` của bài Lab cần chạy:

```bash
# === LAB 01 ===
cd lab1/Ma_nguon
bash lab.sh check     # Kiểm tra môi trường
bash lab.sh test      # Chạy kiểm thử môi trường
bash lab.sh extend    # Chạy bộ quét tự động (SHELL_CASES=4 FAILED=0)
bash lab.sh run       # Mở web giới thiệu tại http://127.0.0.1:8101

# === LAB 02 ===
cd ../../lab2/Ma_nguon
bash lab.sh check
bash lab.sh test      # Chạy test OOP (TESTS=5 FAILED=0)
bash lab.sh extend    # Chạy test mở rộng (TESTS=8 FAILED=0)
bash lab.sh run       # Chạy Demo kiểm tra tương thích API trên terminal

# === LAB 03 ===
cd ../../lab3/Ma_nguon
bash lab.sh check
bash lab.sh test      # Chạy test bộ lọc & form (TESTS=7 FAILED=0)
bash lab.sh extend    # Chạy test đa bộ lọc (TESTS=8 FAILED=0)
bash lab.sh run       # Khởi chạy Web Server tại http://127.0.0.1:8103
                      # Truy cập http://127.0.0.1:8103/api-diff.php để dùng công cụ So sánh API
```

---

## 📄 Giấy phép (License)
Dự án được phân phối dưới giấy phép nguồn mở **MIT License**. Chi tiết xem tại [LICENSE](LICENSE).
