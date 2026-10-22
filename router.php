<?php
/**
 * Router cho máy chủ PHP tích hợp:  php -S localhost:8000 router.php
 * Chặn truy cập trực tiếp các thư mục nội bộ (Apache/XAMPP đã chặn bằng .htaccess).
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('#^/(app|pages|database|cli|tests)(/|$)|^/(config\.php|\.env)$#', $path)) {
    http_response_code(403);
    exit('Forbidden');
}
return false; // phục vụ tệp tĩnh hoặc index.php như bình thường
