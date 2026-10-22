<?php
defined('APP') or exit;

if (is_post()) {
    $_SESSION = [];
    session_regenerate_id(true);
    flash('success', 'Đã đăng xuất.');
}
redirect('login');
