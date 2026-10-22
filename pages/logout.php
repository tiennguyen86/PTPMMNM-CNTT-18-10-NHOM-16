<?php
defined('APP') or exit;

// Luôn xóa phiên (kể cả GET) để tránh session treo; form UI vẫn dùng POST + CSRF.
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool)$p['secure'], (bool)$p['httponly']);
}
session_destroy();
session_start();
session_regenerate_id(true);
flash('success', 'Đã đăng xuất.');
redirect('login');
