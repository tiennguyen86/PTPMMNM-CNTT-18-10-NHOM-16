<?php
defined('APP') or exit;

$GLOBALS['config'] = require __DIR__ . '/../config.php';

date_default_timezone_set($GLOBALS['config']['timezone']);
mb_internal_encoding('UTF-8');
error_reporting(E_ALL);

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_name('schemacheck');
session_start();

require __DIR__ . '/Schema/SchemaTree.php';
require __DIR__ . '/Schema/SchemaParser.php';
require __DIR__ . '/Schema/SchemaInferrer.php';
require __DIR__ . '/Schema/SchemaDiff.php';
require __DIR__ . '/Schema/SchemaValidator.php';

require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/install.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/services.php';
require __DIR__ . '/view.php';

set_exception_handler(function (Throwable $e) {
    http_response_code(500);
    $msg = $e instanceof PDOException
        ? 'Lỗi cơ sở dữ liệu: ' . $e->getMessage() . ' — kiểm tra lại cấu hình trong config.php.'
        : $e->getMessage();
    echo '<!doctype html><meta charset="utf-8"><title>Lỗi</title>'
        . '<div style="font-family:system-ui;max-width:720px;margin:60px auto;padding:24px;border:1px solid #fecaca;background:#fef2f2;border-radius:12px">'
        . '<h2 style="margin-top:0;color:#b91c1c">Đã xảy ra lỗi</h2><p>' . htmlspecialchars($msg) . '</p>'
        . '<pre style="font-size:12px;white-space:pre-wrap;color:#7f1d1d">' . htmlspecialchars($e->getFile() . ':' . $e->getLine()) . '</pre>'
        . '<a href="index.php">← Về trang chủ</a></div>';
});
