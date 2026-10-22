<?php
/**
 * Cấu hình ứng dụng.
 *
 * Ưu tiên biến môi trường / tệp .env (xem .env.example).
 * - driver 'sqlite': chạy ngay, không cần MySQL (dữ liệu ở database/app.sqlite).
 * - driver 'mysql' : XAMPP / Laragon / Docker (port mặc định 3307).
 */
require_once __DIR__ . '/app/env.php';
load_env(__DIR__ . '/.env');

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443)
    || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');

return [
    'app_name' => env('APP_NAME', 'API SchemaCheck'),
    'debug'    => env_bool('APP_DEBUG', false),

    // Cho phép trang đăng ký công khai (tắt khi không còn demo)
    'allow_register' => env_bool('APP_ALLOW_REGISTER', true),

    'login_max_attempts'   => env_int('APP_LOGIN_MAX_ATTEMPTS', 8),
    'login_window_minutes' => env_int('APP_LOGIN_WINDOW_MINUTES', 15),

    'db' => [
        'driver'      => env('DB_DRIVER', 'sqlite'),
        'sqlite_path' => __DIR__ . '/database/app.sqlite',

        'host'        => env('DB_HOST', '127.0.0.1'),
        'port'        => env_int('DB_PORT', 3307),
        'database'    => env('DB_DATABASE', 'mnm_db'),
        'username'    => env('DB_USERNAME', 'mnm_user'),
        'password'    => (string)env('DB_PASSWORD', ''),
    ],

    // Dung lượng tối đa của tệp mô tả / dữ liệu mẫu (upload hoặc dán text)
    'upload_max_bytes' => env_int('UPLOAD_MAX_BYTES', 2 * 1024 * 1024),

    // Gọi API live khi kiểm tra dữ liệu
    'http_timeout_seconds' => env_int('HTTP_TIMEOUT_SECONDS', 10),
    'http_max_bytes'       => env_int('HTTP_MAX_BYTES', 2 * 1024 * 1024),

    'timezone' => env('APP_TIMEZONE', 'Asia/Ho_Chi_Minh'),
    'https'    => $isHttps,
];
