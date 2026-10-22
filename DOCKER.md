# Chạy 5 lab bằng Docker

Docker được cài ở cấp hệ điều hành. Các lệnh dưới đây dùng cấu hình chung tại thư mục gốc
repository.

## Chuẩn bị

```bash
docker compose build
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
