<?php
/**
 * Cấu hình ứng dụng.
 *
 * - driver 'sqlite': chạy ngay, không cần cài MySQL (dữ liệu nằm ở database/app.sqlite).
 * - driver 'mysql' : dùng với XAMPP / Laragon. Ứng dụng tự tạo CSDL + bảng + dữ liệu mẫu
 *   ở lần chạy đầu tiên (hoặc import thủ công file database/schema_mysql.sql bằng phpMyAdmin).
 */
return [
    'app_name' => 'API SchemaCheck',

    'db' => [
        'driver'      => 'sqlite',            // 'sqlite' hoặc 'mysql'
        'sqlite_path' => __DIR__ . '/database/app.sqlite',

        'host'        => '127.0.0.1',
        'port'        => 3306,
        'database'    => 'api_schema_checker',
        'username'    => 'root',
        'password'    => '',
    ],

    // Dung lượng tối đa của tệp mô tả / dữ liệu mẫu được tải lên
    'upload_max_bytes' => 2 * 1024 * 1024,

    'timezone' => 'Asia/Ho_Chi_Minh',
];
