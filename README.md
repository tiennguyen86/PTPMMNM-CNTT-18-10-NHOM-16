# Phát Triển Phần Mềm Nguồn Mở (PTPMMNM)

## Thông tin nhóm thực hiện
- **Lớp**: CNTT-18-10
- **Nhóm**: 16
- **Khoa**: Công nghệ Thông tin - Trường Đại học Đại Nam (FIT-DNU)

### Thành viên nhóm:
| STT | Họ và tên | Mã sinh viên | Vai trò |
|:---:|:---|:---:|:---|
| 1 | **Nguyễn Hữu Tiến** | 1871020569 | Nhóm trưởng |
| 2 | **Nguyễn Việt Anh** | 1871020047 | Thành viên |
| 3 | **Nguyễn Tiến Thuận** | 1871020562 | Thành viên |

---

## Danh sách bài thực hành (Labs)

| Bài | Tên bài | Trạng thái | Kiểm thử tự động |
|:---|:---|:---:|:---:|
| [Lab 01](lab1/) | Vận hành dự án trên Linux và viết shell script | **Hoàn thành** | `CHECK_ENV=PASS`<br>`SHELL_CASES=4 FAILED=0` |
| [Lab 02](lab2/) | Lập trình hướng đối tượng PHP & Xử lý chính sách đăng ký | **Hoàn thành** | `TESTS=5 FAILED=0`<br>`TESTS=8 FAILED=0` |
| [Lab 03](lab3/) | Xử lý Form, kiểm tra dữ liệu và Bộ lọc sự kiện đa điều kiện | **Hoàn thành** | `TESTS=7 FAILED=0`<br>`TESTS=8 FAILED=0` |
| [Lab 04](lab4/) | Quản lý phiên làm việc, Cookie và Xác thực | Khởi tạo | Đang phát triển |
| [Lab 05](lab5/) | Tích hợp cơ sở dữ liệu PostgreSQL | Khởi tạo | Đang phát triển |

---

## Chi tiết kết quả thực hiện Lab 1, Lab 2, Lab 3

### 1. Lab 01: Quét cú pháp mã nguồn (`scripts/scan-source.sh`)
- Kiểm tra tính hợp lệ của thư mục và sự tồn tại của lệnh `php` (trả về mã 2 nếu không hợp lệ).
- Duyệt qua từng tệp `.php`, kiểm tra cú pháp bằng `php -l`.
- Đếm tổng số tệp, số tệp vượt qua (pass), số tệp lỗi cú pháp (fail).
- Xử lý thư mục rỗng trả về mã 2; có lỗi cú pháp trả về mã 1; tất cả tệp hợp lệ trả về mã 0.
- **Kết quả kiểm thử**:
  - `bash lab.sh test`: `CHECK_ENV=PASS`
  - `bash lab.sh extend`: `SHELL_CASES=4 FAILED=0`

### 2. Lab 02: Chính sách kiểm soát đăng ký (`RegistrationPolicy.php`)
- Hoàn thiện phương thức `decide(int $capacity, int $registered, bool $open, bool $waitingEnabled): string`.
- Kiểm tra tính hợp lệ: sức chứa `<= 0`, số đăng ký `< 0` hoặc `> sức chứa` -> trả về `'rejected'`.
- Kiểm tra trạng thái đóng: `!$open` -> trả về `'rejected'`.
- Kiểm tra còn chỗ: `$registered < $capacity` -> trả về `'confirmed'`.
- Trường hợp hết chỗ: `$waitingEnabled` là `true` -> trả về `'waiting'`, ngược lại trả về `'rejected'`.
- **Kết quả kiểm thử**:
  - `bash lab.sh test`: `TESTS=5 FAILED=0`
  - `bash lab.sh extend`: `TESTS=8 FAILED=0`

### 3. Lab 03: Bộ lọc sự kiện kết hợp (`EventFilter.php` & `public/index.php`)
- Hoàn thiện hàm `filterEvents(array $events, string $query, string $category = '', bool $availableOnly = false): array`.
- Kết hợp 3 điều kiện lọc bằng logic `AND`:
  - Từ khóa tìm kiếm: không phân biệt hoa thường (`stripos`).
  - Phân loại (category): khớp chính xác loại đã chọn hoặc bỏ qua nếu để trống.
  - Tình trạng còn chỗ: kiểm tra `$event['registered'] < $event['capacity']`.
- Tích hợp giao diện trên `public/index.php` với dropdown chọn danh mục và checkbox chỉ xem sự kiện còn chỗ; duy trì trạng thái bộ lọc sau khi gửi request `GET`.
- **Kết quả kiểm thử**:
  - `bash lab.sh test`: `TESTS=7 FAILED=0`
  - `bash lab.sh extend`: `TESTS=8 FAILED=0`

---

## Yêu cầu môi trường & Hướng dẫn chạy

### 1. Môi trường yêu cầu
- Hệ điều hành: Ubuntu / WSL2
- PHP: Phiên bản từ 8.1 trở lên (`php-cli`)
- Git

### 2. Lệnh kiểm tra và chạy từng bài lab

Di chuyển vào thư mục `Ma_nguon` của bài lab tương ứng:

```bash
# Ví dụ chạy Lab 1
cd lab1/Ma_nguon
bash lab.sh check    # Kiểm tra môi trường
bash lab.sh test     # Chạy bộ kiểm thử cơ bản
bash lab.sh extend   # Chạy bộ kiểm thử mở rộng
bash lab.sh run      # Khởi chạy web server (http://127.0.0.1:8101)
```

```bash
# Ví dụ chạy Lab 2
cd lab2/Ma_nguon
bash lab.sh check
bash lab.sh test
bash lab.sh extend
bash lab.sh run
```

```bash
# Ví dụ chạy Lab 3
cd lab3/Ma_nguon
bash lab.sh check
bash lab.sh test
bash lab.sh extend
bash lab.sh run      # Khởi chạy web server (http://127.0.0.1:8103)
```

---

## Giấy phép (License)
Dự án được phân phối dưới giấy phép **MIT License**. Xem chi tiết tại tệp [LICENSE](LICENSE).
