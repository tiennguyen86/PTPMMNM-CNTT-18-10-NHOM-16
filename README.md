# API SchemaCheck

**Đề tài 2 – Xây dựng công cụ kiểm tra thay đổi và tính tương thích của cấu trúc dữ liệu API**

Ứng dụng web viết bằng **PHP thuần (không dùng framework) + PDO + MySQL/SQLite**, giao diện HTML/CSS/JavaScript thuần.
Hệ thống lưu bản mô tả cấu trúc dữ liệu của từng endpoint theo phiên bản, so sánh hai phiên bản để chỉ ra thay đổi
và mức ảnh hưởng tới ứng dụng đang dùng API, đồng thời kiểm tra dữ liệu API trả về có đúng bản mô tả hay không.

---

## 1. Cách chạy

Yêu cầu: **PHP 8.0 trở lên** có bật `pdo_mysql` (dùng MySQL) hoặc `pdo_sqlite` (dùng SQLite).

### Cách A – XAMPP / Laragon (MySQL, xem được bằng phpMyAdmin)

1. Chép cả thư mục `MNM` vào `C:\xampp\htdocs\` (Laragon: `C:\laragon\www\`).
2. Mở `config.php`, đổi `'driver' => 'sqlite'` thành `'driver' => 'mysql'` (mặc định `root`, mật khẩu rỗng – sửa nếu máy bạn khác).
3. Bật **Apache** và **MySQL** trong XAMPP Control Panel.
4. Mở trình duyệt: <http://localhost/MNM/>

Ở lần chạy đầu, ứng dụng **tự tạo CSDL `api_schema_checker`, các bảng và dữ liệu mẫu**.
Nếu muốn tự import: vào phpMyAdmin → tạo CSDL `api_schema_checker` (utf8mb4_unicode_ci) → Import tệp `database/schema_mysql.sql`.

### Cách B – Chạy nhanh không cần MySQL (SQLite)

```bash
cd MNM
php -S localhost:8000 router.php
```

Mở <http://localhost:8000>. Dữ liệu nằm trong `database/app.sqlite` (tự tạo).

> **Làm mới dữ liệu mẫu:** SQLite – xóa tệp `database/app.sqlite`; MySQL – xóa (drop) CSDL `api_schema_checker`. Lần truy cập sau sẽ tự tạo lại.

### Tài khoản dùng thử

| Tài khoản  | Mật khẩu   | Vai trò                                            |
|------------|------------|----------------------------------------------------|
| `admin`    | `admin123` | Quản trị hệ thống (quản lý người dùng, sửa bộ quy tắc) |
| `phutrach` | `123456`   | Người phụ trách API của dự án mẫu                   |
| `dev1`     | `123456`   | Thành viên – được sửa bản mô tả                    |
| `tester`   | `123456`   | Thành viên – chỉ xem                               |

---

## 2. Các chức năng theo yêu cầu đề tài

| Yêu cầu | Đã làm | Trang |
|---|---|---|
| **1. Quản lý dự án, endpoint, thành viên, phiên bản mô tả; nhập bản mô tả từ tệp** | Tạo/sửa/xóa dự án và endpoint; thêm/xóa thành viên, phân quyền *Người phụ trách API – Được sửa – Chỉ xem*; thêm phiên bản bằng cách tải tệp `.json` hoặc dán nội dung; tải về tệp gốc hoặc JSON Schema chuẩn hóa | Dự án & endpoint |
| **2. Đọc cấu trúc lồng nhau, mảng, kiểu cơ bản, trường bắt buộc; báo rõ cấu trúc chưa hỗ trợ** | Bộ đọc `SchemaParser` chuyển JSON Schema thành cây cấu trúc; nút **Phân tích thử** hiển thị cây + bảng báo cáo (đường dẫn, mức, nội dung) trước khi lưu. Có thể **suy ra cấu trúc từ JSON mẫu** | Thêm phiên bản mô tả, Xem phiên bản |
| **3. So sánh hai phiên bản; chỉ ra trường thay đổi và ảnh hưởng theo bộ quy tắc đã công bố** | `SchemaDiff` so sánh cả request và response, phân loại từng thay đổi theo 18 quy tắc (Phá vỡ / Cần lưu ý / Tương thích), đoán trường bị đổi tên, kết luận và gợi ý tăng phiên bản (Semantic Versioning). Bộ quy tắc công khai ở trang **Bộ quy tắc**, admin chỉnh được mức độ | So sánh phiên bản, Bộ quy tắc |
| **4. Kiểm tra dữ liệu phản hồi mẫu; lưu lần kiểm tra; tìm, lọc lỗi** | `SchemaValidator` kiểm tra kiểu, trường bắt buộc, null, enum, định dạng (email, date-time, uuid, uri…), ràng buộc độ dài/giá trị, trường lạ. Mỗi lần kiểm tra được lưu kèm danh sách lỗi; lọc theo dự án, endpoint, kết quả, ngày, loại lỗi, mức lỗi, từ khóa; thống kê loại lỗi và trường hay lỗi | Kiểm tra dữ liệu, Lần kiểm tra, Tra cứu lỗi |

### Phân quyền trong dự án

| Quyền | Xem mô tả, so sánh, kiểm tra dữ liệu | Thêm/sửa endpoint & phiên bản | Quản lý thành viên, sửa/xóa dự án |
|---|:-:|:-:|:-:|
| Chỉ xem | ✔ | | |
| Được sửa mô tả | ✔ | ✔ | |
| Người phụ trách API | ✔ | ✔ | ✔ |

Phiên bản mô tả đã lưu **không sửa được** (để giữ lịch sử); muốn thay đổi thì dùng **"Tạo bản mới từ bản này"**.

---

## 3. Kịch bản demo gợi ý

1. Đăng nhập `phutrach` → **Dự án & endpoint** → *Hệ thống bán hàng (demo)*.
2. Mở `GET /api/users/{id}` → **So sánh** v1.0.0 → v2.0.0: thấy *đổi tên* `phone → phoneNumber`, *đổi kiểu* `age`, *xóa* `address.zip`… → kết luận **Không tương thích ngược**, gợi ý tăng phiên bản chính (MAJOR).
3. Mở `POST /api/orders` → so sánh: request **thêm trường bắt buộc** `paymentMethod` ("yêu cầu thêm thông tin") → phá vỡ tương thích; response `total` integer → number → cần lưu ý.
4. Mở `GET /api/products` → phiên bản v1.0.0: bảng **báo cáo cấu trúc chưa hỗ trợ** (`$ref`, `oneOf`, tuple, kiểu hợp…).
5. **Thêm phiên bản mô tả**: chọn tệp trong thư mục `samples/` → *Phân tích thử* → *Lưu*. Thử định dạng *JSON mẫu* với `samples/sample_for_infer.json`.
6. **Kiểm tra dữ liệu**: tải `samples/sample_user_error.json` với bản v2.0.0 → 9 lỗi, 2 cảnh báo → vào **Tra cứu lỗi** lọc theo loại lỗi / từ khóa `address`.
7. Đăng nhập `tester` để thấy giới hạn quyền *Chỉ xem*; đăng nhập `admin` để sửa mức độ trong **Bộ quy tắc** rồi so sánh lại.

---

## 4. Định dạng bản mô tả

**JSON Schema** (một object JSON). Hỗ trợ:

- `type`: `object`, `array`, `string`, `number`, `integer`, `boolean`, `null`; cho phép null bằng `"type": ["string", "null"]` hoặc `"nullable": true`
- `properties`, `required`, `items`, `additionalProperties: false`
- `enum`, `const`, `format` (`email`, `date`, `date-time`, `time`, `uri`, `uuid`, `ipv4`, `ipv6`, `hostname`)
- `minimum`, `maximum`, `minLength`, `maxLength`, `minItems`, `maxItems`, `pattern`
- Từ khóa chú thích (`title`, `description`, `example`…) và `x-*` được bỏ qua

**Báo là chưa hỗ trợ** (nhánh đó bị bỏ qua khi so sánh/kiểm tra và được đánh dấu rõ):
`$ref`, `oneOf`, `anyOf`, `allOf`, `not`, `if/then/else`, `patternProperties`, `prefixItems`/mảng tuple,
kiểu hợp nhiều kiểu (`["string","integer"]`), `additionalProperties` dạng schema, `dependencies`…

**JSON mẫu**: dán một phản hồi thật; hệ thống tự suy ra cấu trúc (mọi trường xuất hiện là bắt buộc; với mảng object,
trường chỉ bắt buộc khi có ở mọi phần tử).

---

## 5. Cấu trúc thư mục

```
MNM/
├── index.php              Front controller – định tuyến index.php?page=...
├── config.php             Cấu hình CSDL (sqlite / mysql)
├── router.php             Router cho "php -S" (chặn truy cập thư mục nội bộ)
├── app/
│   ├── bootstrap.php      Khởi tạo: session, nạp thư viện, xử lý lỗi
│   ├── db.php             Kết nối PDO, hàm truy vấn q()/row()/rows()/insert()/update()
│   ├── install.php        Tạo bảng + dữ liệu mẫu ở lần chạy đầu
│   ├── auth.php           Đăng nhập, phân quyền theo dự án
│   ├── services.php       Nghiệp vụ: đọc mô tả, so sánh, lưu lần kiểm tra
│   ├── helpers.php        Hàm tiện ích (escape, CSRF, flash, phân trang…)
│   ├── view.php           Bố cục trang, badge, icon
│   ├── default_rules.php  Bộ quy tắc tương thích mặc định
│   └── Schema/
│       ├── SchemaParser.php     Đọc JSON Schema → cây cấu trúc, báo cấu trúc chưa hỗ trợ
│       ├── SchemaInferrer.php   Suy ra cấu trúc từ JSON mẫu
│       ├── SchemaDiff.php       So sánh hai cây theo bộ quy tắc
│       ├── SchemaValidator.php  Kiểm tra dữ liệu theo cây cấu trúc
│       └── SchemaTree.php       Tiện ích: duyệt cây, hiển thị, xuất JSON Schema
├── pages/                 Mỗi tệp là một trang (xử lý form + hiển thị)
├── assets/css/app.css     Giao diện
├── assets/js/app.js       Kiểm tra cú pháp JSON, nạp tệp, tab, lọc bảng
├── database/              app.sqlite (tự tạo) và schema_mysql.sql (cho phpMyAdmin)
└── samples/               Tệp mô tả và dữ liệu mẫu để thử nhập từ tệp
```

## 6. Cơ sở dữ liệu

| Bảng | Nội dung |
|---|---|
| `users` | Tài khoản (mật khẩu băm bằng `password_hash`), vai trò hệ thống `admin`/`user` |
| `projects` | Dự án |
| `project_members` | Thành viên dự án và quyền `owner` / `editor` / `viewer` |
| `endpoints` | Địa chỉ API (phương thức + đường dẫn) thuộc dự án |
| `schema_versions` | Phiên bản mô tả: nội dung gốc, cây cấu trúc đã chuẩn hóa (request/response), báo cáo cấu trúc chưa hỗ trợ |
| `compat_rules` | Bộ quy tắc tương thích, mức độ riêng cho request và response |
| `validation_runs` | Lần kiểm tra dữ liệu mẫu: phiên bản dùng, dữ liệu, kết quả, số lỗi |
| `validation_errors` | Từng lỗi của lần kiểm tra: mức, loại, đường dẫn, nội dung, giá trị mong đợi/thực tế |

## 7. Bảo mật

Truy vấn dùng prepared statement (chống SQL injection), mọi dữ liệu in ra đều được escape (chống XSS),
mọi form POST có CSRF token, mật khẩu băm bằng `password_hash`, kiểm tra quyền ở phía máy chủ cho từng thao tác,
thư mục `app/`, `pages/`, `database/` bị chặn truy cập trực tiếp (`.htaccess` / `router.php`).
