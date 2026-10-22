# Chạy 5 lab bằng Docker

Docker được cài ở cấp hệ điều hành. Các lệnh dưới đây dùng cấu hình chung tại thư mục gốc
repository.

## Chuẩn bị

```bash
docker compose --profile lab4 --profile lab5 build
```

## Lab 1-4

Mỗi lab dùng cùng image PHP 8.3, có Git và các công cụ cần thiết:

```bash
docker compose --profile lab1 run --rm lab1 bash lab.sh test
docker compose --profile lab1 run --rm lab1 bash lab.sh extend

docker compose --profile lab2 run --rm lab2 php tests/run.php
docker compose --profile lab2 run --rm lab2 php tests/extension.php

docker compose --profile lab3 run --rm lab3 bash lab.sh test
docker compose --profile lab3 run --rm lab3 bash lab.sh extend
docker compose --profile lab4 run --rm lab4 bash lab.sh test
docker compose --profile lab4 run --rm lab4 bash lab.sh extend
```

Chạy trang web bằng profile tương ứng:

```bash
docker compose --profile lab1 up lab1  # http://127.0.0.1:8101
docker compose --profile lab3 up lab3  # http://127.0.0.1:8103
docker compose --profile lab4 up lab4  # http://127.0.0.1:8104
```

## Lab 5

Profile `lab5` tự khởi động PostgreSQL, nạp `database/schema.sql` lần đầu và chờ database healthy
trước khi chạy PHP:

```bash
docker compose build
docker compose --profile lab5 run --rm lab5 bash lab.sh check
docker compose --profile lab5 run --rm lab5 bash lab.sh test
docker compose --profile lab5 run --rm lab5 bash lab.sh extend
docker compose --profile lab5 up lab5  # http://127.0.0.1:8105
```

Thông tin database trong môi trường Docker:

- Host: `db`
- Port: `5432`
- Database: `communitypass_lab_v5`
- User: `communitypass_lab_v5`
- Password: `communitypass_lab_v5_dev`

Dữ liệu database nằm trong volume Docker `lab5-db-data`. Xóa volume sẽ xóa dữ liệu lab:

```bash
docker compose --profile lab5 down -v
```

## Phần hoàn thiện Lab 4–5

Lab 4 đã triển khai guard và nối vào form xác nhận/hoàn tất; Lab 5 đã triển khai truy vấn thống kê có tham số. Entrypoint PHP tạo `.local/sessions` sau khi mount mã nguồn để các profile Lab 4–5 chạy được ngay từ checkout mới.

```bash
docker compose --profile lab4 --profile lab5 build
docker compose --profile lab4 run --rm lab4 bash lab.sh test
docker compose --profile lab4 run --rm lab4 bash lab.sh extend
docker compose --profile lab4 run --rm lab4 php tests/guard-regression.php
docker compose --profile lab5 run --rm lab5 bash lab.sh test
docker compose --profile lab5 run --rm lab5 bash lab.sh extend
docker compose --profile lab5 run --rm lab5 php tests/stats-regression.php
```

Kết quả đã kiểm chứng bằng PHP CLI và PostgreSQL 16 thật (môi trường kiểm tra không có Docker Engine):

| Lab | Mẫu | Tích hợp | Mở rộng | Hồi quy bổ sung |
| --- | --- | --- | --- | --- |
| 4 | 6/6 | — | 7/7 | 14/14 |
| 5 | 6/6 | 7/7 | 4/4 | 4/4 |

Đã kiểm tra HTTP: nhập/sửa/hủy/hoàn tất, CSRF, hai phiên độc lập, sai ID và nháp hết hạn; Lab 5 lưu/trùng và giữ nháp khi PostgreSQL ngừng hoạt động, thử lại sau khi khôi phục. Dữ liệu còn sau khi khởi động lại PostgreSQL. Kiểm thử giao diện demo của cả hai lab cũng đạt.

Chưa chạy build hoặc container Docker tại môi trường này; cần chạy các lệnh Compose phía trên trên máy có Docker Engine để kiểm chứng phần container.
